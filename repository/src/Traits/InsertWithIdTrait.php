<?php
/**
 * Created by PhpStorm.
 * User: lumin
 * Date: 16-12-12
 * Time: 下午6:00
 */

namespace NEUQOJ\Repository\Traits;

trait InsertWithIdTrait{
    function insertWithId(array $data)
    {
        return $this->model->insertGetId($data);
    }
}