<?php namespace Ratiw\Api;

use Illuminate\Foundation\Application;
use League\Fractal\Manager;
use Input;

class BaseApiController extends ApiController
{
    /**
     * @var Application
     */
    protected $app;

    protected $sortColumn = 'id';
    protected $sortDirection = 'asc';

    protected $model = null;
    protected $transformer = null;
    protected $transformerBasePath = '';

    protected $eagerLoads = [];

    protected $filters = [];
    protected $searchQuery = '';

    function __construct(Application $app, Manager $fractal)
    {
        parent::__construct($fractal);

        $this->app = $app;

        $this->init();
    }

    public function init()
    {
        $this->transformerBasePath = str_finish(\Config::get('api.transformer_path', ''), '\\');

        is_null($this->model) and $this->model = $this->guessModelName();
        is_null($this->transformer) and $this->transformer = $this->getTransformer($this->model);

        $this->model = $this->app->make($this->model);
        $this->transformer = $this->app->make($this->transformer);

        $this->setSearchQuery(Input::get('q', ''));
        $this->setSortOrder(Input::get('sort', ''));
        $this->setPerPage(Input::get('per_page', $this->perPage));
        $this->setFilters(Input::all());
        $page = Input::get('page', '');

        if (Input::has('fields'))
        {
            $this->transformer->transformOnly(
                preg_split('/\s*,\s*/', Input::get('fields'))
            );
        }
    }

    protected function guessModelName()
    {
        $class = class_basename(get_called_class());
        if (ends_with($class, 'Controller'))
        {
            return str_replace('Controller', '', $class);
        }

        return $class;
    }

    protected function getTransformer($modelName)
    {
        $modelName = class_basename($modelName);
        $className = $this->transformerBasePath . "${modelName}Transformer";

        return class_exists($className) ? $className : 'Ratiw\Api\BaseTransformer';
    }

    public function index()
    {
        /*
            // set metadata
            $meta = [
                'base_url' => Request::url(),
                'search'   => $q,
                'filter'   => $filter,
                'sort'     => $sort
            ];
        */
        $meta = [];

        $paginator = $this->query()->paginate($this->perPage);

        return $this->respondWithPagination($paginator, $this->transformer, $meta);
    }

    /**
     * @return array
     */
    private function extractFilters($input)
    {
        return array_except($input, ['q', 'sort', 'page', 'per_page', 'fields', 'include']);
    }

    protected function query()
    {
        $query = $this->model->with($this->eagerLoads)
            ->orderBy($this->sortColumn, $this->sortDirection);

        $query = $this->applySearch($query);

        return $this->applyFilters($query, $this->filters);
    }

    /**
     * @param $query
     */
    protected function applySearch($query)
    {
        if ( ! empty($this->searchQuery))
        {
            $query->where(function ($query)
            {
                $this->search($query, $this->searchQuery);
            });
        }

        return $query;
    }

    protected function search($query, $q)
    {
        return $query->where('code', 'like', "$q%");
    }

    public function show($id)
    {
        $data = $this->model->whereIn('id', explode(',', $id))->get();

        if (! $data) {
            return $this->errorNotFound();
        }

        return $this->respondWithCollection($data, $this->transformer);
    }

    public function setSearchQuery($q)
    {
        $this->searchQuery = $q;
    }

    public function setFilters($input)
    {
        $this->filters = $this->extractFilters($input);
    }

    public function applyFilters($query, $filters)
    {
        $filters = $this->transformer->untransform($filters);

        $query->where(function($query) {
            $this->getDefaultFilters($query);
        });

        foreach ($filters as $key => $value)
        {
            $this->applyFilter($query, $key, $value);
        }

        return $query;
    }

    /**
     * @param $query
     * @param $key
     * @param $value
     */
    private function applyFilter($query, $key, $value)
    {
        if (is_array($value) and !empty($value))
        {
            if (count($value) > 2)
            {
                $query->where($value[0], $value[1], $value[2]);
            }
            else
            {
                $query->where($value[0], $value[1]);
            }
        }
        else
        {
            $query->where($key, $value);
        }
    }

    public function setSortOrder($sort)
    {
        $sort = trim($sort);
        if (empty($sort)) return;

        if (starts_with($sort, '-')) {
            $this->sortDirection = 'desc';
        }
        else{
            $this->sortDirection = 'asc';
        }
        $this->sortColumn = $this->transformer->untransform(str_replace('-', '', $sort));
    }

    public function getSortOrder()
    {
        $dir = ($this->sortDirection == 'desc') ? '-' : '';

        return $dir . $this->sortColumn;
    }

    public function getDefaultFilters($query)
    {
        return $query;
    }

    private function array_implode($glue, $array, $sign = '=')
    {
        $str = '';
        foreach ($array as $key => $value)
        {
            empty($str) or $str .= $glue;

            $str .= $key.$sign.$value;
        }

        return $str;
    }

    /*
        public function destroy($id)
        {
            $data = $this->model->find($id);

            if ( ! $data ) {
                return $this->errorNotFound();
            }

            if ( $data->delete() ) {
                return $this->respondOK('Resource has been deleted.');
            }
            else {
                return $this->errorInternalError();
            }
        }
    */
}
