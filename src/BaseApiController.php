<?php namespace Ratiw\Api;

use Auth;
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

    protected $defaultFilters = [];
    protected $filters = [];
    protected $searchQuery;

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
        $this->setSearchQuery($q = Input::get('q', ''));
        $this->setSortOrder($sort = Input::get('sort', ''));
        $this->setPerPage($per_page = Input::get('per_page', ''));
        $page = Input::get('page', '');

        if (Input::has('fields'))
        {
            $this->transformer->transformOnly(
                preg_split('/\s*,\s*/', Input::get('fields'))
            );
        }

        $filters = $this->extractFilters(Input::all());
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

        if (empty($q))
        {
            $data = $this->query()
                ->where(function($query) use($filters) {
                    $this->applyFilters($query, $filters);
                })
                ->orderBy($this->sortColumn, $this->sortDirection)
                ->paginate($this->perPage);
        }
        else
        {
            $data = $this->search($this->query(), $q)
                ->where(function($query) use($filters) {
                    $this->applyFilters($query, $filters);
                })
                ->orderBy($this->sortColumn, $this->sortDirection)
                ->paginate($this->perPage);

            $data->appends(compact('q', 'sort', 'page', 'per_page', 'filters', 'fields', 'include'));
        }
        return $this->respondWithPagination($data, $this->transformer, $meta);
    }

    /**
     * @return array
     */
    private function extractFilters($input)
    {
        $filters = array_except($input, ['q', 'sort', 'page', 'per_page', 'fields', 'include']);

        return $filters;
    }

    protected function query()
    {
        return $this->model->with($this->eagerLoads);
    }

    protected function search($query, $searchStr)
    {
        return $query->where('code', 'like', "$searchStr%");
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

    public function applyFilters($query, $filters)
    {
        $filters = $this->transformer->untransform($filters);

        if ( ! empty($this->defaultFilters))
        {
            $filters = array_merge($this->defaultFilters, $filters);
        }

        foreach ($filters as $key => $value)
        {
            $this->applyFilter($query, $key, $value);
        }

        return $this;
    }

    /**
     * @param $query
     * @param $key
     * @param $value
     */
    private function applyFilter($query, $key, $value)
    {
        if (is_array($value))
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