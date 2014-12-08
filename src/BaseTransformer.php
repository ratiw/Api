<?php namespace Ratiw\Api;

use League\Fractal\TransformerAbstract;

class BaseTransformer extends TransformerAbstract
{
    protected $fields = [];

    public function transform($data)
    {
        return $data->toArray();
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
                $output[$fields[$key]] = $value;
            }
            else{
                $output[$key] = $value;
            }
        }

        return $output;
    }
}