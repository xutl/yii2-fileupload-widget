<?php
/**
 * @link http://www.tintsoft.com/
 * @copyright Copyright (c) 2012 TintSoft Technology Co. Ltd.
 * @license http://www.tintsoft.com/license/
 */

namespace xutl\fileupload;

use Yii;
use yii\base\Action;
use yii\helpers\Html;
use yii\web\Response;
use yii\base\Exception;
use yii\web\UploadedFile;
use yii\web\BadRequestHttpException;
use yuncms\attachment\AttachmentTrait;
use yuncms\attachment\components\Uploader;

/**
 * Class UploadAction
 * @package xutl\fileupload\actions
 */
class UploadAction extends Action
{
    use AttachmentTrait;

    /**
     * @var string file input name.
     */
    public $uploadParam = 'file';

    /**
     * @var string Validator name
     */
    public $onlyImage = true;

    /**
     * @var bool 是否允许批量上传
     */
    public $multiple = false;

    /**
     * @var string 参数指定文件名
     */
    public $uploadQueryParam = 'file_param';

    private $_config = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->controller->enableCsrfValidation = false;

        if (Yii::$app->request->get($this->uploadQueryParam)) {
            $this->uploadParam = Yii::$app->request->get($this->uploadQueryParam);
        }

        $this->_config['maxSize'] = $this->getMaxUploadByte();
        if ($this->multiple) {
            $this->_config['maxFiles'] = (int)(ini_get('max_file_uploads'));
        }
        if ($this->onlyImage !== true) {
            $this->_config['extensions'] = $this->getSetting('fileAllowFiles');
        } else {
            $this->_config['extensions'] = $this->getSetting('imageAllowFiles');
            $this->_config['checkExtensionByMimeType'] = true;
            $this->_config['mimeTypes'] = 'image/*';
        }
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (Yii::$app->request->isPost) {
            $files = UploadedFile::getInstancesByName($this->uploadParam);
            if (!$this->multiple) {
                $res = [$this->uploadOne($files[0])];
            } else {
                $res = $this->uploadMore($files);
            }
            return ['files' => $res];
        } else {
            throw new BadRequestHttpException('Only POST is allowed');
        }
    }

    /**
     * 批量上传
     * @param array $files
     * @return array
     */
    private function uploadMore(array $files)
    {
        $res = [];
        foreach ($files as $file) {
            $result = $this->uploadOne($file);
            $res[] = $result;
        }
        return $res;
    }

    /**
     * 单文件上传
     * @param UploadedFile $file
     * @return array|mixed
     */
    private function uploadOne(UploadedFile $file)
    {
        try {
            $uploader = new Uploader(['config' => $this->_config]);
            $uploader->up($file);
            $fileInfo = $uploader->getFileInfo();
            $result = [
                'name' => Html::encode($file->name),
                'url' => $fileInfo['url'],
                'path' => $fileInfo['url'],
                'extension' => $file->extension,
                'type' => $file->type,
                'size' => $file->size
            ];
            if ($this->onlyImage !== true) {
                $result['filename'] = $result['name'];
            }

        } catch (Exception $e) {
            $result = [
                'error' => $e->getMessage()
            ];
        }
        return $result;
    }
}
