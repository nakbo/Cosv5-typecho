<?php
/**
 * 腾讯对象储存
 *
 * @package Cosv5
 * @author 南博工作室
 * @version 1.0.0
 * @link https://github.com/krait-team/Cosv5-typecho
 */

class Cosv5_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return string
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Upload')->uploadHandle = ['Cosv5_Plugin', 'uploadHandle'];
        Typecho_Plugin::factory('Widget_Upload')->modifyHandle = ['Cosv5_Plugin', 'modifyHandle'];
        Typecho_Plugin::factory('Widget_Upload')->deleteHandle = ['Cosv5_Plugin', 'deleteHandle'];
        Typecho_Plugin::factory('Widget_Upload')->attachmentHandle = ['Cosv5_Plugin', 'attachmentHandle'];
        Typecho_Plugin::factory('Widget_Upload')->attachmentDataHandle = ['Cosv5_Plugin', 'attachmentDataHandle'];

        return _t("Cosv5 插件启动成功");
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return string
     */
    public static function deactivate()
    {
        return _t("Cosv5 已被禁用");
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $secretId = new Typecho_Widget_Helper_Form_Element_Text('secretId',
            NULL, NULL,
            _t('SecretId'),
            _t('<a href="https://console.qcloud.com/cam/capi" target="_blank">获取SecretId</a>'));
        $form->addInput($secretId->addRule('required', _t('SecretId不能为空！')));

        $secretKey = new Typecho_Widget_Helper_Form_Element_Text('secretKey',
            NULL, NULL,
            _t('SecretKey'),
            _t('<a href="https://console.qcloud.com/cam/capi" target="_blank">获取SecretKey</a>'));
        $form->addInput($secretKey->addRule('required', _t('SecretKey不能为空！')));

        $region = new Typecho_Widget_Helper_Form_Element_Radio('region', [
            'ap-beijing-1' => _t('北京一区（华北）'),
            'ap-beijing' => _t('北京'),
            'ap-shanghai' => _t('上海（华东）'),
            'ap-guangzhou' => _t('广州（华南）'),
            'ap-chengdu' => _t('成都（西南）'),
            'ap-chongqing' => _t('重庆'),
            'ap-singapore' => _t('新加坡'),
            'ap-hongkong' => _t('香港'),
            'na-toronto' => _t('多伦多'),
            'eu-frankfurt' => _t('法兰克福'),
            'ap-mumbai' => _t('孟买'),
            'ap-seoul' => _t('首尔'),
            'na-siliconvalley' => _t('硅谷'),
            'na-ashburn' => _t('弗吉尼亚'),
            'ap-shenzhen-fsi' => _t('深圳金融'),
            'ap-shanghai-fsi' => _t('上海金融'),
            'ap-beijing-fsi' => _t('北京金融'),
        ], 'ap-shanghai', _t('选择bucket节点'));
        $form->addInput($region->addRule('required', _t('bucket节点不能为空！')));

        $bucket = new Typecho_Widget_Helper_Form_Element_Text('bucket',
            NULL, NULL, '存储桶名称',
            _t('<a href="https://console.cloud.tencent.com/cos5/bucket" target="_blank">获取存储桶名称</a> 例如: bucket-123456'));
        $form->addInput($bucket->addRule('required', _t('Bucket名称不能为空！')));

        $uploadDir = new Typecho_Widget_Helper_Form_Element_Text('uploadDir',
            NULL, Widget_Upload::UPLOAD_DIR, '存储基础路径',
            _t('填写上传的基础路径, 比如:' . Widget_Upload::UPLOAD_DIR));
        $form->addInput($uploadDir);

        $uploadPath = new Typecho_Widget_Helper_Form_Element_Text('uploadPath',
            NULL, '{basedir}/{year}/{month}/{filename}', '存储相对路径',
            _t('填写上传的相对路径<br>{basedir} 存储基础路径; {year} 年份; {month} 月份; {filename} 文件名 <br>比如: {basedir}/{year}/{month}/{filename}<br>表示例如 ' . Widget_Upload::UPLOAD_DIR . '/2021/03/03/123456.png'));
        $form->addInput($uploadPath);

        $uploadFilename = new Typecho_Widget_Helper_Form_Element_Text('uploadFilename',
            NULL, '{randname}', '存储文件名字',
            _t('填写上传的存储文件名字<br>{randname} 随机数字名字; {basename} 原文件名字'));
        $form->addInput($uploadFilename);

        $domain = new Typecho_Widget_Helper_Form_Element_Text('domain',
            NULL, 'https://',
            _t('自定义域名'),
            _t('使用的域名,必填,请带上http://或https://，可使用默认域名或自定义域名<br>在bucket中的域名管理, 默认域名形如：http://bucket-123456.cos.ap-shanghai.myqcloud.com<br>自定义域名形如：https://cos.tencent.com'));
        $form->addInput($domain->addRule('required', _t('cos域名不能为空！')));
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 上传文件处理函数
     *
     * @access public
     * @param array $file 上传的文件
     * @param null $path
     * @return mixed
     * @throws Typecho_Plugin_Exception
     */
    public static function uploadHandle($file, $path = NULL)
    {
        // check name
        // exist in Widget_Upload::uploadHandle

        $ext = Cosv5_Plugin::getSafeName($file['name']);

        if (!Widget_Upload::checkFileType($ext)) {
            return false;
        }

        // body
        if (isset($file['tmp_name'])) {
            $tmp_open = fopen($file['tmp_name'], "rb");
            $body = fread($tmp_open, $file['size']);
            fclose($tmp_open);
        } else if (isset($file['bytes'])) {
            // check
            if (!isset($file['mime'])) {
                return false;
            }
            $body = $file['bytes'];
        } else {
            return false;
        }

        $date = new Typecho_Date();
        $option = Helper::options()->plugin("Cosv5");

        if (empty($path)) {
            $randname = sprintf('%u', crc32(uniqid())) . '.' . $ext;
            $filename = trim(str_replace(
                ['{basename}', '{randname}'],
                [$file['name'], $randname],
                $option->uploadFilename
            ));

            if (empty($filename)) {
                $filename = $randname;
            }

            $path = trim(str_replace(
                ['{basedir}', '{year}', '{month}', '{filename}'],
                [rtrim($option->uploadDir, '/'), $date->year, $date->month, $filename],
                $option->uploadPath
            ));

            if (empty($path)) {
                $path = (defined('__TYPECHO_UPLOAD_DIR__') ? __TYPECHO_UPLOAD_DIR__ : Widget_Upload::UPLOAD_DIR)
                    . '/' . $date->year . '/' . $date->month . '/' . $filename;
            }
        }

        // client
        $client = Cosv5_Plugin::getClient($option);

        try {
            $client->upload($option->bucket, $path, $body);
        } catch (Exception $e) {
            return false;
        }

        if (!isset($file['size'])) {
            $file['size'] = filesize($path);
        }

        if (!isset($file['mime'])) {
            $file['mime'] = Typecho_Common::mimeContentType($file['tmp_name']);
        }

        return [
            'name' => $file['name'],
            'path' => $path,
            'size' => $file['size'],
            'type' => $ext,
            'mime' => $file['mime']
        ];
    }

    /**
     * 修改文件处理函数
     *
     * @access public
     * @param array $content 老文件
     * @param array $file 新上传的文件
     * @return mixed
     * @throws Typecho_Exception
     */
    public static function modifyHandle($content, $file)
    {
        // check name
        // exist in Widget_Upload::modifyHandle

        //获取扩展名
        $ext = Cosv5_Plugin::getSafeName($file['name']);

        //判定是否是允许的文件类型
        if ($content['attachment']->type != $ext) {
            return false;
        }

        // 上传路径
        $result = Cosv5_Plugin::uploadHandle($file, $content['attachment']->path);

        return [
            'name' => $content['attachment']->name,
            'path' => $content['attachment']->path,
            'size' => $result['size'],
            'type' => $content['attachment']->type,
            'mime' => $content['attachment']->mime
        ];
    }

    /**
     * 文件删除
     *
     * @access public
     * @param array $content 当前文件信息
     * @return mixed
     * @throws Typecho_Exception
     */
    public static function deleteHandle($content)
    {
        // client
        $option = Helper::options()->plugin("Cosv5");
        $client = Cosv5_Plugin::getClient($option);

        try {
            $error = $client->deleteObject([
                'Bucket' => $option->bucket,
                'Key' => $content['attachment']->path
            ]);
            return !$error;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取实际文件数据
     *
     * @access public
     * @param array $content
     * @return string
     * @throws Typecho_Exception
     */
    public static function attachmentDataHandle($content)
    {
        // client
        $option = Helper::options()->plugin("Cosv5");
        $client = Cosv5_Plugin::getClient($option);

        try {
            $result = $client->getObject([
                'Bucket' => $option->bucket,
                'Key' => $content['attachment']->path
            ]);

            return $result['Body'];
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取实际文件绝对访问路径
     *
     * @access public
     * @param array $content 文件相关信息
     * @return string
     * @throws Typecho_Exception
     */
    public static function attachmentHandle(array $content)
    {
        $option = Helper::options()->plugin("Cosv5");

        if (!preg_match('/http(s)?:\/\/[\w\d\.\-\/]+$/is', $option->domain)) {
            return false;
        }

        return Typecho_Common::url($content['attachment']->path, $option->domain);
    }

    /**
     * 获取安全的文件名
     *
     * @param string $name
     * @static
     * @access private
     * @return string
     */
    private static function getSafeName(&$name)
    {
        $name = str_replace(array('"', '<', '>'), '', $name);
        $name = str_replace('\\', '/', $name);
        $name = false === strpos($name, '/') ? ('a' . $name) : str_replace('/', '/a', $name);
        $info = pathinfo($name);
        $name = substr($info['basename'], 1);

        return isset($info['extension']) ? strtolower($info['extension']) : '';
    }

    /**
     * @param $option
     * @return \Qcloud\Cos\Client
     */
    public static function getClient($option)
    {
        require_once 'cos-sdk-v5.phar';
        return new Qcloud\Cos\Client([
            'region' => $option->region,
            'schema' => 'https',
            'credentials' => [
                'secretId' => $option->secretId,
                'secretKey' => $option->secretKey
            ]
        ]);
    }
}
