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

    protected $eagerLoads = [];

    function __construct(Application $app, Manager $fractal)
    {
        parent::__construct($fractal);

        $this->app = $app;

        is_null($this->model) and $this->model = $this->guessModelName();
        is_null($this->transformer) and $this->transformer = $this->getTransformer($this->model);

        $this->model = $this->app->make($this->model);
        $this->transformer = $this->app->make($this->transformer);

        $this->init();
    }

    public function init()
    {
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

        return class_exists($className) ? $className : $this->transformerBasePath . "BaseTransformer";
    }

    public function index()
    {
        $q = Input::get('q', '');
        $this->setSortOrder(Input::get('sort', ''));
        $this->setPerPage(Input::get('per_page', ''));

        $filters = array_except(Input::all(), ['q', 'sort', 'page', 'per_page', 'fields', 'embeds', 'include']);
        /*
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
                    $this->setFilters($query, $filters);
                })
                ->orderBy($this->sortColumn, $this->sortDirection)
                ->paginate($this->perPage);
        }
        else
        {
            $data = $this->search($this->query(), $q)
                ->where(function($query) use($filters) {
                    $this->setFilters($query, $filters);
                })
                ->orderBy($this->sortColumn, $this->sortDirection)
                ->paginate($this->perPage);
            $data->appends(compact('q', 'filter', 'sort'));
        }
        return $this->respondWithPagination($data, new $this->transformer, $meta);
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

        return $this->respondWithCollection($data, new $this->transformer);
    }

    public function setFilters($query, $filters)
    {
        $filters = $this->transformer->untransform($filters);

        foreach ($filters as $key => $value)
        {
            $query->where($key, $value);
        }

        return $this;
    }

    public function setPerPage($perPage)
    {
        if (empty($perPage)) return;

        $this->perPage = $perPage;
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