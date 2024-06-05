<?php

namespace App\Services;

// use App\Base\BaseRepository;

use hpsynapse\moduser\Facades\UserAuth;

use App\Models\PostReference as MPostReference;
use App\Models\PostReferenceGlobal as PostReferenceGlobal;

class PostReference //extends BaseRepository
{

    private $_tmpRefIdTry = 0;
    private $_tmpRefIdNow = '';
    private $error = '';

    public function error()
    {
        return $this->error;
    }

    /**
     * Get Tenant Model
     *
     * @param integer $tenantId
     * @return \Illuminate\Database\Eloquent\Model
     */
    private function getModel($tenantId)
    {
        if ($tenantId) {
            return new MPostReference();
        } else {
            return new PostReferenceGlobal();
        }
    }

    /**
     * generate & get ref_id
     *
     * @return false|string    string ref_id
     */
    public function getPostRef($formId, $tenantId = false, $userId = false)
    {
        if (empty($formId)) {
            $this->error = 'Form ID tidak boleh kosong';
            return false;
        }

        $tenantId = empty($tenantId) ? config('tenant.id', 0) : $tenantId;
        $userId = empty($userId) ? (UserAuth::user('id') ?? 0) : $userId;

        if (!$userId) {
            $this->error = 'User tidak terdefinisi';
            return false;
        }

        // generate refId yg uniq
        $refId = $this->doGetPostRef($formId, $tenantId, $userId);

        // pastikan sekali lagi refid tidak double
        if($this->getModel($tenantId)
            ->where('form_id', $formId)
            ->where('ref_id', $refId)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 0)->count()>=2){
            $refId = $this->doGetPostRef($formId, $tenantId, $userId);
        }

        return $refId;
    }
        private function doGetPostRef($formId, $tenantId = false, $userId = false)
        {
            $this->_tmpRefIdNow = now()->format('YmdHis');
            $refId = $this->generateRandomrefId($formId, $tenantId, $userId);
            while ($this->getModel($tenantId)
                ->select('ref_id')
                ->where('form_id', $formId)
                ->where('ref_id', $refId)
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('status', 0)
                ->exists()
            ) {
                $refId = $this->generateRandomrefId($formId, $tenantId, $userId);
            }

            $this->getModel($tenantId)->create([
                'ref_id' => $refId,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'form_id' => $formId,
                'status' => 0, // new input
            ]);
            return $refId;
        }

    /**
     * Generate Random Ref ID
     *
     * @param integer $formId
     * @param integer $tenantId
     * @param integer $userId
     * @return string
     */
    private function generateRandomrefId($formId, $tenantId, $userId)
    {
        $tmpKey = $tenantId . '-'
            . $userId . '-'
            . $formId . '-'
            . $this->_tmpRefIdNow . '-'
            . $this->_tmpRefIdTry;
        $this->_tmpRefIdTry++;
        return md5($tmpKey);
    }

    /**
     * Mark Post Ref ID as Used
     *
     * @param integer $formId
     * @param string $refId
     * @param integer $tenantId
     * @param integer $userId
     * @return boolean True jika valid, False jika gagal atau jika sudah tidak valid
     */
    public function usePostRef($formId, $refId, $tenantId = false, $userId = false)
    {
        if (empty($formId)) {
            $this->error = 'Form ID tidak boleh kosong';
            return false;
        }
        if (empty($refId)) {
            $this->error = 'Ref ID tidak boleh kosong';
            return false;
        }

        $tenantId = $tenantId ?: config('tenant.id', 0);
        $userId = $userId ?: UserAuth::user('id') ?? 0;

        $tmp = $this->getModel($tenantId)
            ->select('ref_id')
            ->where('form_id', $formId)
            ->where('ref_id', $refId)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 0);

        if ($tmp->delete()>0) {
            return true;
        }

        $this->error = 'Ref ID tidak ditemukan';
        return false;
    }
}
