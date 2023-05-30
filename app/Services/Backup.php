<?php

namespace App\Services;

use App\Models\Backup as BModel;

use App\Base\BaseRepository;

class Backup extends BaseRepository
{
    public function __construct(BModel $model)
    {
        $this->model = $model;
    }

    /**
     * Create new Backup data and delete the oldest row if limit reached
     *
     * @param array $data
     * @param integer $maxLimit
     * @return void
     */
    public function create($data, $maxLimit = 10)
    {
        $this->_create($this->model, $data);
        if ($this->model->count() > $maxLimit) {
            $this->deleteFirst();
        }
    }

    /**
     * Delete Oldest backup
     *
     * @return void
     */
    public function deleteFirst()
    {
        $backup = BModel::orderBy('id', 'ASC')->first();

        //delete file
        exec('rm -rf "' . $backup->path . '"');

        $backup->delete();
    }

    /**
     * Delete backup by condition
     *
     * @param array $where
     * @return void
     */
    public function delete($where)
    {
        $backup = $this->_getOne($this->model, $where);
        if ($backup) {
            $this->_delete($this->model, $where);
            //delete file
            exec('rm -rf "' . $backup['path'] . '"');
        }
    }
}
