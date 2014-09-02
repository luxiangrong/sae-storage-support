<?php

/*
  Plugin Name: Sae Storage Support
  Plugin URI:
  Description: 让WordPress支持SAE的Storage服务。<br/>由于SAE的PHP Runtime环境并不提供持久性本地IO能力，所以需要Storage来提供存储服务，此插件可以让wordpress使用SAE Storage来存储文件
  Author: LuXiangrong
  Version: 0.1.beta
  Author URI: http://hanshansnow.sinaapp.com/
 */
require_once 'class-wp-image-editor-sae.php';
require_once 'functions.php';

define('SAE_STORAGE_DOMAIN' , 'http://%s.stor.sinaapp.com');

class StorageSAE
{

    public function __construct()
    {
        register_activation_hook(__FILE__, array($this, 'install' ));
        
        add_filter('wp_handle_upload', array( $this, 'moveToSaeStorage' ));
        
        add_filter('upload_dir', array($this, 'resetUploadDir'));

        add_filter('wp_get_attachment_url', array($this, 'convert_attachment_url_to_sae'), 10, 2);
        
        add_filter('get_attached_file', array($this, 'get_sae_attached_file'), 10 , 2);
        
        add_filter('_wp_relative_upload_path', array($this, 'sae_relative_upload_path'), 10, 2);

        add_filter('wp_image_editors', array($this, 'wp_image_editors'), 10, 1);
        
        add_filter('wp_delete_file', array($this, 'sae_delete_file'), 10, 1);
        
        add_action('admin_menu', array($this, 'add_sae_settings_page'));
    }

    public function  add_sae_settings_page() {
        add_options_page('SAE Settings', 'SAE Settings', 8, __FILE__, array($this, 'sae_settings_page'));
    }
    
    public function sae_settings_page() {
        if(!class_exists('SaeStorage')) {
            wp_die( '此插件需要在SAE环境下使用' );
        }
        
        $saeOptions = array();
        
        $storage = new SaeStorage();
        
        if(isset($_POST['sae_domain'])) {
        	$saeOptions['sae_domain'] = $_POST['sae_domain'];
        }
        if(isset($_POST['sae_uploads'])) {
        	$saeOptions['sae_uploads'] = $_POST['sae_uploads'];
        }
        
        if($saeOptions !== array()) {
        	update_option('sae_options', $saeOptions);
        	?>
	        <div class="updated"><p><strong>设置已保存！</strong></p></div>
	        <?php
        }
        
    	
    	$domainList = $storage->listDomains ();
    	
    	$appName = $storage->getAppname();
    	$domains = array();
    	
    	$saeOptions = get_option('sae_options');
        $sae_domain = $saeOptions['sae_domain'];
        $sae_uploads = $saeOptions['sae_uploads'];
//         $sae_uploads = get_option('sae_uploads', 'uploads');
?>
<div class="wrap" style="margin: 10px;">
    <h2>新浪云存储 设置</h2>
    <p></p>
    <form method="post" action="">
        <table class="form-table">
            <tbody>
            	<tr>
                    <th scope="row"><label for="sae_domain">Domain选择</label></th>
                    <td>
                        <?php if(count($domainList) > 0):?>
                        <span>http://</span>
                        <?php if($sae_domain):?>
                        	<?php echo $sae_domain;?>
                        	<input type="hidden" name="sae_domain" value="<?php echo $sae_domain?>" />
                        <?php else:?>
                        <select name="sae_domain">
                    		<?php foreach($domainList as $domain):?>
                    		<option value="<?php echo $domain;?>" <?php if($sae_domain == $domain): ?>selected="true"<?php endif;?>><?php echo $domain;?></option>
                    		<?php endforeach;?>
                    	</select>
                        <?php endif;?>
                    	<span>/</span>
                        <input name="sae_uploads" type="text" id="uploads" value="<?php echo $sae_uploads;?>" class="regular-text" />
                    	<?php else:?>
                    		<p><a target="_blank" href="http://sae.sina.com.cn/?m=storage&a=index&app_id=<?php echo $appName;?>">创建新的Doman</a></p>
                    	<?php endif;?>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td><input type="submit" name="" id="" class="button action" value="保存"></td>
                </tr>
            </tbody>
        </table>
    </form>
</div>
<?php
    }
    
    /**
     * 将附件的url地址变换为SAE Storage地址
     * 
     * @param type $url
     * @param type $id
     * @return type
     */
    public function convert_attachment_url_to_sae($url, $id)
    {
    	$saeOptions = get_option('sae_options');
        $file = get_post_meta($id, '_wp_attached_file', true);
        $sae_domain = $saeOptions['sae_domain'];
        return trailingslashit(sprintf(SAE_STORAGE_DOMAIN, $sae_domain)) . $file;;
    }
    
    /**
     * 获取附件在Sae Storage中的url地址
     * 
     * @param string $file
     * @param int $id
     * @return Ambigous <mixed, string, multitype:, boolean, unknown, string>|unknown
     */
    public function get_sae_attached_file($file, $id) {
		$saeOptions = get_option('sae_options');
		$sae_domain = $saeOptions['sae_domain'];
		$uploads = $saeOptions['sae_uploads'];
    	$filepath = get_post_meta($id, '_wp_attached_file', true);
    	if (strpos($filepath, trailingslashit(sprintf(SAE_STORAGE_DOMAIN, $sae_domain)).$uploads) == 0) {
    		return $filepath;
    	}
    	return $file;
    }
    
    /**
     * 获取文件在Sae Storage中的相对路径
     * 
     * @param unknown $new_path
     * @param unknown $path
     * @return mixed
     */
    public function sae_relative_upload_path($new_path, $path) {
		$domainName = sae_storage_domain_name();
        if(strpos($new_path, "saestor://$domainName/") == 0) {
            $new_path = str_replace( "saestor://$domainName/", '', $new_path );
        }
        return $new_path;
    }
        

    /**
     * 由wp_image_editors过滤器触发的方法
     * 使用WP_Image_Editor_SAEImage替换默认的Image Editor
     *  
     * @param array $editors
     * @return array $editors
     */
    public function wp_image_editors($editors)
    {
        return array('WP_Image_Editor_SAEImage');
    }

    /**
     * 由upload_dir过滤器触发的方法。
     * 由于SAE的限制，将上传目录重置为SAE_TMP_PATH目录，上传的文件先移动到此目录，再移动到SAE Storage中
     * 
     * @param array $uploadDirInfo 上传文件夹的信息
     * @return array
     */
    public function resetUploadDir($uploadDirInfo)
    {
    	$saeOptions = get_option('sae_options');
		$sae_domain = $saeOptions['sae_domain'];
    	$uploads = $saeOptions['sae_uploads'];
    	
    	if(is_null($uploads)) {
			$base_url = trailingslashit(sprintf(SAE_STORAGE_DOMAIN, $sae_domain));
		} else {
			$base_url = trailingslashit(sprintf(SAE_STORAGE_DOMAIN, $sae_domain)).$uploads;
		}
    	
        $uploadDirInfo ['path'] = SAE_TMP_PATH . $uploads . $uploadDirInfo ['subdir'];
        $uploadDirInfo ['basedir'] = SAE_TMP_PATH . $uploads;
        $uploadDirInfo ['url'] = trailingslashit($base_url) . $uploadDirInfo ['subdir'];
        $uploadDirInfo ['base_url'] = $base_url;

        return $uploadDirInfo;
    }

    /**
     * 由wp_handle_upload过滤器触发的方法
     * 将上传的文件移动到SAE Storage中
     * 
     * @param array $fileInfo
     *     'file' 上传后的文件路径
     *     'url' 上传后的文件url信息
     *     'type' 文件类型
     * @return SAE Storage相关的文件信息
     */
    public function moveToSaeStorage($fileInfo)
    {
    	$saeStorage = new SaeStorage();
        $domain = sae_storage_domain_name();

        $destFileName = str_replace(SAE_TMP_PATH, '', $fileInfo ['file']);
        $info = pathinfo($destFileName);
        $dir = $info['dirname'];
        $baseName = $info['basename'];
        $destFileName = $dir . '/' . sae_unique_filename($domain, $dir, $baseName);
        $srcFileName = $fileInfo ['file'];

        $result = $saeStorage->upload($domain, $destFileName, $srcFileName, null, false);

        return array(
            'file' => 'saestor://'. $domain . '/' . $destFileName,
            'url' => $result,
            'type' => $fileInfo ['type']
        );
    }
    
    /**
     * 由wp_delete_file过滤器触发的方法
     * 将附件从SAE Storage中删除
     * 
     * @param string $file
     * @return mixed
     */
    public function sae_delete_file($file) {
		$saeStorage = new SaeStorage();
		$domain = sae_storage_domain_name();
	
		$upload_dir = wp_upload_dir();
		$fileName = basename($file);
		
		$filePath = str_replace(SAE_TMP_PATH, '', $upload_dir['path'].'/' . $fileName);
		$saeStorage->delete($domain, $filePath);
		
		return $filePath;
	}


    public function install()
    {
        
    }

}

new StorageSAE();
