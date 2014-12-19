<?php namespace Ratiw\Api;

use League\Fractal\TransformerAbstract;

class BaseTransformer extends TransformerAbstract
{
    protected $fields = [];

    protected $transformOnlyFields = [];

    public function transformOnly(array $only)
    {
        $this->transformOnlyFields = $only;
    }

    public function getTransformOnlyFields()
    {
        return $this->transformOnlyFields;
    }

    protected function modelTransform($data)
    {
        return $data->toArray();
    }

    public function transform($data)
    {
        $data = $this->modelTransform($data);

        if ( ! empty($this->transformOnlyFields))
        {
            $data = array_only($data, $this->transformOnlyFields);
        }

        return $data;
    }

    public function untransform($input)
    {
        if (empty($this->fields)) return $input;

        if (is_array($input))
        {
            return $this->array_change_key($input, $this->fields);
        }
        else
        {
            return array_key_exists($input, $this->fields) ? $this->fields[$input] : $input;
        }
    }

    public function array_change_key(array $input, array $fields = [])
    {
        $output = [];

        foreach ($input as $key => $value)
        {
            if (array_key_exists($key, $fields))
            {
                $output[$fields[$key]] = trim($value);
            }
            else{
                $output[$key] = trim($value);
            }
        }

        return $output;
    }
}