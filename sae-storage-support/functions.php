<?php
/**
 * Get a filename that is sanitized and unique for the given directory.
 *
 * If the filename is not unique, then a number will be added to the filename
 * before the extension, and will continue adding numbers until the filename is
 * unique.
 *
 * The callback is passed three parameters, the first one is the directory, the
 * second is the filename, and the third is the extension.
 *
 * @since 2.5.0
 *
 * @param string $dir
 * @param string $filename
 * @param mixed $unique_filename_callback Callback.
 * @return string New filename, if given wasn't unique.
 */
function sae_unique_filename($domain, $dir, $filename, $unique_filename_callback = null) {
	$stor = new SaeStorage();
	// sanitize the file name before we begin processing
	$filename = sanitize_file_name ( $filename );
	
	// separate the filename into a name and extension
	$info = pathinfo ( $filename );
	$ext = ! empty ( $info ['extension'] ) ? '.' . $info ['extension'] : '';
	$name = basename ( $filename, $ext );
	
	// edge case: if file is named '.ext', treat as an empty name
	if ($name === $ext)
		$name = '';
		
		// Increment the file number until we have a unique file to save in $dir. Use callback if supplied.
	if ($unique_filename_callback && is_callable ( $unique_filename_callback )) {
		$filename = call_user_func ( $unique_filename_callback, $dir, $name, $ext );
	} else {
		$number = '';
		
		// change '.ext' to lower case
		if ($ext && strtolower ( $ext ) != $ext) {
			$ext2 = strtolower ( $ext );
			$filename2 = preg_replace ( '|' . preg_quote ( $ext ) . '$|', $ext2, $filename );
			
			// check for both lower and upper case extension or image sub-sizes may be overwritten
			while ( $stor->fileExists( $domain , $dir.'/'.$filename ) || $stor->fileExists( $domain , $dir.'/'.$filename2 ) ) {
				$new_number = $number + 1;
				$filename = str_replace ( "$number$ext", "$new_number$ext", $filename );
				$filename2 = str_replace ( "$number$ext2", "$new_number$ext2", $filename2 );
				$number = $new_number;
			}
			return $filename2;
		}
		while ( $stor->fileExists ( $domain , $dir.'/'.$filename ) ) {
			if ('' == "$number$ext")
				$filename = $filename . ++ $number . $ext;
			else
				$filename = str_replace ( "$number$ext", ++ $number . $ext, $filename );
		}
	}
	
	return $filename;
}

function sae_storage_domain_name() {
	$saeOptions = get_option('sae_options');
	$sae_domain = $saeOptions['sae_domain'];
	$arr = explode('-', $sae_domain);
	if(count($arr) == 2) {
		return $arr[1];
	} else {
		return new WP_Error('invalid_domain_name', __('there is a invalid_domain'), $sae_domain);
	}
}

function sae_storage_app_name() {
	$saeOptions = get_option('sae_options');
	$sae_domain = $saeOptions['sae_domain'];
	$arr = explode('-', $sae_domain);
	if(count($arr) == 2) {
		return $arr[0];
	} else {
		return new WP_Error('invalid_app_name', __('there is a invalid_domain'), $sae_domain);
	}
}

function sae_covert_to_wrapper($url) {
	$domain = sae_storage_domain_name();
	$urlInfo = parse_url($url);
	if(preg_match('/.*stor.sinaapp.com/', $urlInfo['host'])) {
		$url = preg_replace('/.*stor.sinaapp.com(.*)/', 'saestor://'.$domain.'$1', $url);
	}
	return $url;
}