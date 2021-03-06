<?php namespace Ratiw\Api;

use Illuminate\Pagination\Paginator;
use Request;
use Exception;
use Illuminate\Http\Response;
use League\Fractal\Manager;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;

class HostNotAllowedException extends Exception {}

class ApiController extends \Controller
{
    const CODE_WRONG_ARGS = 'WRONG-ARGS';
    const CODE_NOT_FOUND = 'NOT-FOUND';
    const CODE_INTERNAL_ERROR = 'INTERNAL-ERROR';
    const CODE_UNAUTHORIZED = 'UNAUTHORIZED';
    const CODE_FORBIDDEN = 'FORBIDDEN';

    protected $statusCode = 200;
    protected $perPage = 10;
    protected $perPageLimit = 100;

    protected $noData = [
        "data" => [],
        "meta" => [
            "pagination" => [
                "count" => 0,
                "total" => 0
            ]
        ]
    ];

    /**
     * @var Manager
     */
    protected $fractal;

    function __construct(Manager $fractal)
    {
        // Need to do Authentication HERE!
        $this->beforeFilter('auth');

        // Check if the request is from allowable hosts
        $this->checkAllowables();

        $this->fractal = $fractal;

        if (Request::has('include'))
        {
            $this->fractal->parseIncludes(Request::get('include'));
        }
    }

    public function checkAllowables()
    {
        $host = Request::header('host');
        $path = Request::path();

        if ( ! $this->checkAllowableHosts($host) or ! $this->checkAllowablePaths()) {
            throw new HostNotAllowedException("host: $host; path: $path");
        }
    }

    public function checkAllowableHosts($host)
    {
        return in_array(
            $host,
            \Config::get('api.allow_hosts', ['localhost', 'localhost:8000'])
        );
    }

    public function checkAllowablePaths()
    {
        $allowablePaths = \Config::get('api.allow_paths', ['api/*']);

        foreach ($allowablePaths as $path)
        {
            if (Request::is($path)) return true;
        }

        return false;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getPerPage()
    {
        return $this->perPage;
    }

    public function setPerPage($value)
    {
        if ($value > $this->perPageLimit)
        {
            throw new \InvalidArgumentException('perPage value cannot exceed ' . $this->perPageLimit);
        }

        $this->perPage = $value;

        return $this;
    }

    /**
     * Generate a Response with a 403 HTTP header and a given message.
     *
     * @return  Response
     */
    public function errorForbidden($message = 'Forbidden')
    {
        return $this->setStatusCode(403)->respondWithError($message, ApiController::CODE_FORBIDDEN);
    }

    /**
     * Generates a Response with a 500 HTTP header and a given message.
     *
     * @return Response
     */
    public function errorInternalError($message = 'Internal Error')
    {
        return $this->setStatusCode(500)->respondWithError($message, ApiController::CODE_INTERNAL_ERROR);
    }

    /**
     * Generates a Response with a 404 HTTP header and a given message.
     *
     * @return Response
     */
    public function errorNotFound($message = 'Resource Not Found')
    {
        return $this->setStatusCode(404)->respondWithError($message, ApiController::CODE_NOT_FOUND);
    }

    /**
     * Generates a Response with a 401 HTTP header and a given message.
     *
     * @return Response
     */
    public function errorUnauthorized($message = 'Unauthorized')
    {
        return $this->setStatusCode(401)->respondWithError($message, ApiController::CODE_UNAUTHORIZED);
    }

    /**
     * Generates a Response with a 400 HTTP header and a given message.
     *
     * @return Response
     */
    public function errorWrongArgs($message = 'Wrong Arguments')
    {
        return $this->setStatusCode(400)->respondWithError($message, ApiController::CODE_WRONG_ARGS);
    }

    protected function respondWithItem($item, $transformer)
    {
        $resource = new Item($item, $transformer);

        $rootScope = $this->fractal->createData($resource);

        return $this->respondWithArray($rootScope->toArray());
    }

    protected function respondWithCollection($collection, $transformer, $paginator = null, $meta = [])
    {
        $resource = new Collection($collection, $transformer);

        if ( ! is_null($paginator))
        {
            $paginatorAdapter = new IlluminatePaginatorAdapter($paginator);

            $this->appendsQueryString($paginatorAdapter);

            $resource->setPaginator($paginatorAdapter);
        }

        empty($meta) or $this->addMetadata($resource, $meta);

        $rootScope = $this->fractal->createData($resource);

        return $this->respondWithArray($rootScope->toArray());
    }

    protected function respondWithPagination($paginator, $transformer, $meta = [])
    {
        return $this->respondWithCollection($paginator->getCollection(), $transformer, $paginator, $meta);
    }

    protected function respondWithArray(array $array, array $headers = [])
    {
        return \Response::json($array, $this->statusCode, $headers);
    }

    protected function respondOK($message = 'Done')
    {
        return $this->setStatusCode(200)->respondWithArray([
            'message' => $message
        ]);
    }

    protected function respondCreated($message = 'Resource successfully created.')
    {
        return $this->setStatusCode(201)->respondWithArray([
            'message' => $message
        ]);
    }

    protected function respondWithError($message, $errorCode)
    {
        if ($this->statusCode === 200)
        {
            trigger_error(
                "You better have a really good reason for erroring on a 200...",
                E_USER_WARNING
            );
        }

        return $this->respondWithArray([
            'error' => [
                'code'      => $errorCode,
                'http_code' => $this->statusCode,
                'message'   => $message,
            ]
        ]);
    }

    protected function addMetadata($resource, $meta)
    {
        foreach ($meta as $metaKey => $metaValue)
        {
            $resource->setMetaValue($metaKey, $metaValue);
        }
    }

    protected function formatResponse(Paginator $paginator, BaseTransformer $transformer = null)
    {
        $data = $paginator->getCollection();

        if ( ! is_null($transformer))
        {
            $data = $this->transformData($data, $transformer);
        }

        $this->appendsQueryString($paginator);

        return [
            "data" => $data,
            "meta" => [
                "pagination" => $this->getPagination($paginator)
            ],
        ];
    }

    protected function transformData($data, $transformer)
    {
        $transformed = [];

        foreach ($data as $item)
        {
            $transformed[] = $transformer->transform($item);
        }

        return $transformed;
    }

    /**
     * @param Paginator $paginator
     * @return Paginator
     */
    protected function appendsQueryString(Paginator $paginator)
    {
        $queryParams = array_diff_key($_GET, array_flip(['page']));
        foreach ($queryParams as $key => $value)
        {
            $paginator->addQuery($key, $value);
        }
    }

    /**
     * @param Paginator $paginator
     * @return array
     */
    protected function getPagination(Paginator $paginator)
    {
        $currentPage = $paginator->getCurrentPage();
        $lastPage = $paginator->getLastPage();

        $links = [];
        if ($currentPage > 1)
        {
            $links['previous'] = $paginator->getUrl($currentPage - 1);
        }
        if ($currentPage < $lastPage)
        {
            $links['next'] = $paginator->getUrl($currentPage + 1);
        }

        $pagination = [];
        $pagination['total'] = $paginator->getTotal();
        $pagination['count'] = $paginator->count();
        $pagination['per_page'] = $paginator->getPerPage();
        $pagination['current_page'] = $currentPage;
        $pagination['total_pages'] = $lastPage;
        $pagination['links'] = $links;

        return $pagination;
    }
}
