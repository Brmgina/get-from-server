<?php
namespace brmgina\WordPress\GetFromServer;
use WP_Error;

/**
 * Enhanced Get From Server Plugin
 * 
 * This plugin allows importing files from the server filesystem into WordPress media library.
 * Enhanced with support for additional file types including ISO, ZIP, RAR, 7Z, TAR, GZ, and BZ2.
 * 
 * @version 1.0.1
 * @author Eng. A7meD KaMeL
 */

const COOKIE = 'frmsvr_path';

class Plugin {

	public static function instance() {
		static $instance = false;
		$class           = static::class;

		return $instance ?: ( $instance = new $class );
	}

	protected function __construct() {
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_filter( 'upload_mimes', [ $this, 'add_iso_mime_type' ] );
		
		// إضافة دعم التحديث التلقائي من GitHub
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_plugin_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_api_call' ], 10, 3 );
	}

	function admin_init() {
		// Register JS & CSS
		wp_register_script( 'get-from-server', plugins_url( '/get-from-server.js', __FILE__ ), array( 'jquery' ), VERSION );
		wp_register_style( 'get-from-server', plugins_url( '/get-from-server.css', __FILE__ ), array(), VERSION );

		add_filter( 'plugin_action_links_' . PLUGIN, [ $this, 'add_upload_link' ] );

		// Handle the path selection early.
		$this->path_selection_cookie();
	}

	function admin_menu() {
		$page_slug = add_media_page(
			__( 'Get From Server', 'get-from-server' ),
			__( 'Get From Server', 'get-from-server' ),
			'upload_files',
			'get-from-server',
			[ $this, 'menu_page' ]
		);
		add_action( 'load-' . $page_slug, function() {
			wp_enqueue_style( 'get-from-server' );
			wp_enqueue_script( 'get-from-server' );
		} );
	}

	function add_upload_link( $links ) {
		if ( current_user_can( 'upload_files' ) ) {
			array_unshift( $links, '<a href="' . admin_url( 'upload.php?page=get-from-server' ) . '">' . __( 'Import Files', 'get-from-server' ) . '</a>' );
		}

		return $links;
	}

	function menu_page() {
		// Handle any imports
		$this->handle_imports();

		echo '<div class="wrap">';
		echo '<h1>' . __( 'Get From Server', 'get-from-server' ) . '</h1>';

		$this->outdated_options_notice();
		$this->main_content();
		$this->language_notice();

		echo '</div>';
	}

	function get_root() {
		// Lock users to either
		// a) The 'GET_FROM_SERVER' constant.
		// b) Their home directory.
		// c) The parent directory of the current install or wp-content directory.

		if ( defined( 'GET_FROM_SERVER' ) ) {
			$root = GET_FROM_SERVER;
		} elseif ( str_starts_with( __FILE__, '/home/' ) ) {
			$root = implode( '/', array_slice( explode( '/', __FILE__ ), 0, 3 ) );
		} else {
			if ( str_starts_with( WP_CONTENT_DIR, ABSPATH ) ) {
				$root = dirname( ABSPATH );
			} else {
				$root = dirname( WP_CONTENT_DIR );
			}
		}

		// Precautions. The user is using the folder placeholder code. Abort for lower-privledge users.
		if (
			str_contains( get_option( 'frmsvr_root', '%' ), '%' )
			&&
			! defined( 'GET_FROM_SERVER' )
			&&
			! current_user_can( 'unfiltered_html' )
		) {
			$root = false;
		}

		return $root;
	}

	function path_selection_cookie() {
		if ( isset( $_REQUEST['path'] ) && current_user_can( 'upload_files' ) ) {
			// تحقق إضافي من المسار قبل حفظه في cookie
			$root = $this->get_root();
			$requested_path = wp_unslash( $_REQUEST['path'] );
			$full_path = realpath( trailingslashit( $root ) . ltrim( $requested_path, '/' ) );
			
			// التحقق من أن المسار ضمن المجلد المسموح
			if ( $full_path && str_starts_with( $full_path, realpath( $root ) ) ) {
				$_COOKIE[ COOKIE ] = $requested_path;

				$parts = parse_url( admin_url(), PHP_URL_HOST );

				setcookie(
					COOKIE,
					wp_unslash( $_COOKIE[ COOKIE ] ),
					time() + 30 * DAY_IN_SECONDS,
					parse_url( admin_url(), PHP_URL_PATH ),
					parse_url( admin_url(), PHP_URL_HOST ),
					'https' === parse_url( admin_url(), PHP_URL_SCHEME ),
					true
				);
			}
		}
	}

	// Handle the imports
	function handle_imports() {

		if ( !empty( $_POST['files'] ) ) {

			check_admin_referer( 'gfs_import' );

			$files = wp_unslash( $_POST['files'] );

			$root = $this->get_root();
			if ( ! $root ) {
				return false;
			}

			flush();
			wp_ob_end_flush_all();

			foreach ( (array)$files as $file ) {
				$filename = trailingslashit( $root ) . ltrim( $file, '/' );

				// تحقق إضافي من المسار لمنع directory traversal
				$real_filename = realpath( $filename );
				if ( !$real_filename || !str_starts_with( $real_filename, realpath( $root ) ) ) {
					echo '<div class="updated error"><p>' . sprintf( __( '<em>%s</em> was <strong>not</strong> imported due to security restrictions.', 'get-from-server' ), esc_html( basename( $file ) ) ) . '</p></div>';
					continue;
				}

				$id = $this->handle_import_file( $real_filename );

				if ( is_wp_error( $id ) ) {
					echo '<div class="updated error"><p>' . sprintf( __( '<em>%s</em> was <strong>not</strong> imported due to an error: %s', 'get-from-server' ), esc_html( basename( $file ) ), $id->get_error_message() ) . '</p></div>';
				} else {
					echo '<div class="updated"><p>' . sprintf( __( '<em>%s</em> has been imported to Media library', 'get-from-server' ), esc_html( basename( $file ) ) ) . '</p></div>';
				}

				flush();
				wp_ob_end_flush_all();
			}
		}
	}

	// Handle an individual file import.
	function handle_import_file( $file ) {
		set_time_limit( 0 );

		$file = wp_normalize_path( $file );

		// Initially, Base it on the -current- time.
		$time = time();

		// A writable uploads dir will pass this test. Again, there's no point overriding this one.
		if ( ! ( ( $uploads = wp_upload_dir( gmdate( 'Y-m-d H:i:s', $time ) ) ) && false === $uploads['error'] ) ) {
			return new WP_Error( 'upload_error', $uploads['error'] );
		}

		// تحقق محسن من نوع الملف
		$wp_filetype = wp_check_filetype( $file, null );
		$type = $wp_filetype['type'];
		$ext  = $wp_filetype['ext'];
		
		// إضافة دعم لملفات إضافية
		if ( !$type ) {
			switch ( $ext ) {
				case 'iso':
					$type = 'application/x-iso9660-image';
					break;
				case 'zip':
					$type = 'application/zip';
					break;
				case 'rar':
					$type = 'application/x-rar-compressed';
					break;
				case '7z':
					$type = 'application/x-7z-compressed';
					break;
				case 'tar':
					$type = 'application/x-tar';
					break;
				case 'gz':
					$type = 'application/gzip';
					break;
				case 'bz2':
					$type = 'application/x-bzip2';
					break;
			}
		}
		
		// التحقق من نوع MIME الفعلي للملف
		$actual_mime = $this->get_actual_mime_type( $file );
		if ( $actual_mime && $type && $actual_mime !== $type ) {
			// إذا كان نوع MIME الفعلي لا يتطابق مع الامتداد
			if ( !current_user_can( 'unfiltered_upload' ) ) {
				return new WP_Error( 'mime_mismatch', __( 'File MIME type does not match its extension.', 'get-from-server' ) );
			}
			// للمستخدمين ذوي الصلاحيات العالية، استخدم نوع MIME الفعلي
			$type = $actual_mime;
		}
		
		if ( ( !$type || !$ext ) && !current_user_can( 'unfiltered_upload' ) ) {
			return new WP_Error( 'wrong_file_type', __( 'Sorry, this file type is not permitted for security reasons.', 'get-from-server' ) );
		}

		// Is the file already in the uploads folder?
		if ( preg_match( '|^' . preg_quote( wp_normalize_path( $uploads['basedir'] ), '|' ) . '(.*)$|i', $file, $mat ) ) {

			$filename = basename( $file );
			$new_file = $file;

			$url = $uploads['baseurl'] . $mat[1];

			$attachment = get_posts( array( 'post_type' => 'attachment', 'meta_key' => '_wp_attached_file', 'meta_value' => ltrim( $mat[1], '/' ) ) );
			if ( !empty($attachment) ) {
				return new WP_Error( 'file_exists', __( 'Sorry, that file already exists in the WordPress media library.', 'get-from-server' ) );
			}

			$time = filemtime( $file ) ?: time();

			// Ok, Its in the uploads folder, But NOT in WordPress's media library.
			if ( preg_match( '|^/?(?P<Ym>(?P<year>\d{4})/(?P<month>\d{2}))|', dirname( $mat[1] ), $datemat ) ) {
				// The file date and the folder it's in are mismatched. Set it to the date of the folder.
				if ( gmdate( 'Y/m', $time ) !== $datemat['Ym'] ) {
					$time = mktime( 0, 0, 0, $datemat['month'], 1, $datemat['year'] );
				}
			}

			// A new time has been found! Get the new uploads folder:
			// A writable uploads dir will pass this test. Again, there's no point overriding this one.
			if ( !(($uploads = wp_upload_dir( gmdate( 'Y-m-d H:i:s', $time) ) ) && false === $uploads['error']) ) {
				return new WP_Error( 'upload_error', $uploads['error'] );
			}
			$url = $uploads['baseurl'] . $mat[1];
		} else {
			$filename = wp_unique_filename( $uploads['path'], basename( $file ) );

			// copy the file to the uploads dir with improved error handling
			$new_file = $uploads['path'] . '/' . $filename;
			if ( !copy( $file, $new_file ) ) {
				error_log( "Get From Server: Failed to copy file from {$file} to {$new_file}" );
				return new WP_Error( 'upload_error', sprintf( __( 'The selected file could not be copied to %s.', 'get-from-server' ), $uploads['path'] ) );
			}

			// Set correct file permissions
			$stat = stat( dirname( $new_file ) );
			$perms = $stat['mode'] & 0000666;
			chmod( $new_file, $perms );
			// Compute the URL
			$url = $uploads['url'] . '/' . $filename;

		}

		// Apply upload filters
		$return = apply_filters( 'wp_handle_upload', array( 'file' => $new_file, 'url' => $url, 'type' => $type ) );
		$new_file = $return['file'];
		$url = $return['url'];
		$type = $return['type'];

		$title = preg_replace( '!\.[^.]+$!', '', basename( $file ) );
		$content = $excerpt = '';

		if ( preg_match( '#^audio#', $type ) ) {
			$meta = wp_read_audio_metadata( $new_file );
	
			if ( ! empty( $meta['title'] ) ) {
				$title = $meta['title'];
			}
	
			if ( ! empty( $title ) ) {
	
				if ( ! empty( $meta['album'] ) && ! empty( $meta['artist'] ) ) {
					/* translators: 1: audio track title, 2: album title, 3: artist name */
					$content .= sprintf( __( '"%1$s" from %2$s by %3$s.', 'get-from-server' ), $title, $meta['album'], $meta['artist'] );
				} elseif ( ! empty( $meta['album'] ) ) {
					/* translators: 1: audio track title, 2: album title */
					$content .= sprintf( __( '"%1$s" from %2$s.', 'get-from-server' ), $title, $meta['album'] );
				} elseif ( ! empty( $meta['artist'] ) ) {
					/* translators: 1: audio track title, 2: artist name */
					$content .= sprintf( __( '"%1$s" by %2$s.', 'get-from-server' ), $title, $meta['artist'] );
				} else {
					$content .= sprintf( __( '"%s".', 'get-from-server' ), $title );
				}
	
			} elseif ( ! empty( $meta['album'] ) ) {
	
				if ( ! empty( $meta['artist'] ) ) {
					/* translators: 1: audio album title, 2: artist name */
					$content .= sprintf( __( '%1$s by %2$s.', 'get-from-server' ), $meta['album'], $meta['artist'] );
				} else {
					$content .= $meta['album'] . '.';
				}
	
			} elseif ( ! empty( $meta['artist'] ) ) {
	
				$content .= $meta['artist'] . '.';
	
			}
	
			if ( ! empty( $meta['year'] ) )
				$content .= ' ' . sprintf( __( 'Released: %d.' ), $meta['year'] );
	
			if ( ! empty( $meta['track_number'] ) ) {
				$track_number = explode( '/', $meta['track_number'] );
				if ( isset( $track_number[1] ) )
					$content .= ' ' . sprintf( __( 'Track %1$s of %2$s.', 'get-from-server' ), number_format_i18n( $track_number[0] ), number_format_i18n( $track_number[1] ) );
				else
					$content .= ' ' . sprintf( __( 'Track %1$s.', 'get-from-server' ), number_format_i18n( $track_number[0] ) );
			}
	
			if ( ! empty( $meta['genre'] ) )
				$content .= ' ' . sprintf( __( 'Genre: %s.', 'get-from-server' ), $meta['genre'] );
	
		// Use image exif/iptc data for title and caption defaults if possible.
		} elseif ( 0 === strpos( $type, 'image/' ) && $image_meta = @wp_read_image_metadata( $new_file ) ) {
			if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) ) {
				$title = $image_meta['title'];
			}
	
			if ( trim( $image_meta['caption'] ) ) {
				$excerpt = $image_meta['caption'];
			}
		}

		// Construct the attachment array
		$attachment = [
			'post_mime_type' => $type,
			'guid'           => $url,
			'post_parent'    => 0,
			'post_title'     => $title,
			'post_name'      => $title,
			'post_content'   => $content,
			'post_excerpt'   => $excerpt,
			'post_date'      => current_time( 'mysql' ),
			'post_date_gmt'  => gmdate( 'Y-m-d H:i:s', $time ),
		];

		$attachment = apply_filters( 'gfs-import_details', $attachment, $file, 0, 'current' );

		// Save the data
		$id = wp_insert_attachment( $attachment, $new_file, 0 );
		if ( !is_wp_error( $id ) ) {
			$data = wp_generate_attachment_metadata( $id, $new_file );
			wp_update_attachment_metadata( $id, $data );
		}

		return $id;
	}

	/**
	 * Add additional MIME types to WordPress allowed types
	 * 
	 * @param array $mimes Array of allowed MIME types
	 * @return array Modified array of MIME types
	 */
	function add_iso_mime_type( $mimes ) {
		// إضافة دعم لملفات ISO
		$mimes['iso'] = 'application/x-iso9660-image';
		
		// إضافة دعم لملفات أخرى شائعة
		$mimes['zip'] = 'application/zip';
		$mimes['rar'] = 'application/x-rar-compressed';
		$mimes['7z'] = 'application/x-7z-compressed';
		$mimes['tar'] = 'application/x-tar';
		$mimes['gz'] = 'application/gzip';
		$mimes['bz2'] = 'application/x-bzip2';
		
		return $mimes;
	}

	/**
	 * Get the actual MIME type of a file using finfo
	 * 
	 * @param string $file Path to the file
	 * @return string|false MIME type or false on failure
	 */
	private function get_actual_mime_type( $file ) {
		if ( !function_exists( 'finfo_open' ) ) {
			return false;
		}

		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		if ( !$finfo ) {
			return false;
		}

		$mime_type = finfo_file( $finfo, $file );
		finfo_close( $finfo );

		return $mime_type;
	}

	protected function get_default_dir() {
		$root = $this->get_root();

		if ( str_starts_with( WP_CONTENT_DIR, $root ) ) {
			return WP_CONTENT_DIR;
		}

		return $root;
	}

	// Create the content for the page
	function main_content() {

		$url = admin_url( 'upload.php?page=get-from-server' );

		$root = $this->get_root();
		if ( ! $root ) {
			return; // Intervention required.
		}

		$cwd = $this->get_default_dir();
		if ( ! empty( $_COOKIE[ COOKIE ] ) ) {
			$requested_path = wp_unslash( $_COOKIE[ COOKIE ] );
			$full_path = realpath( trailingslashit( $root ) . ltrim( $requested_path, '/' ) );
			
			// تحقق إضافي من المسار
			if ( $full_path && str_starts_with( $full_path, realpath( $root ) ) ) {
				$cwd = $full_path;
			}
		}

		// Validate it.
		if ( ! str_starts_with( $cwd, $root ) ) {
			$cwd = $root;
		}

		$cwd_relative = substr( $cwd, strlen( $root ) );

		// Make a list of the directories the user can enter.
		$dirparts = [];
		$dirparts[] = '<a href="' . esc_url( add_query_arg( 'path', rawurlencode( '/' ), $url ) ) . '">' . esc_html( trailingslashit( $root ) ) . '</a> ';

		$dir_path = '';
		foreach ( array_filter( explode( '/', $cwd_relative ) ) as $dir ) {
			$dir_path .= '/' . $dir;
			$dirparts[] = '<a href="' . esc_url( add_query_arg( 'path', rawurlencode( $dir_path ), $url ) ) . '">' . esc_html( $dir ?: basename( $root ) ) . '/</a> ';
		}

		$dirparts = implode( '', $dirparts );

		// Sort alphabetically correctly..
		$sort_by_text = function( $a, $b ) {
			return strtolower( $a['text'] ) <=> strtolower( $b['text'] );
		};

		// Get a list of files to show.
		$nodes = glob( rtrim( $cwd, '/' ) . '/*' ) ?: [];

		$directories = array_flip( array_filter( $nodes, function( $node ) {
			return is_dir( $node );
		} ) );

		$get_import_root = function( $path ) use ( &$get_import_root ) {
			if ( ! is_readable( $path ) ) {
				return false;
			}

			$files = glob( $path . '/*' );
			if ( ! $files ) {
				return false;
			}

			$has_files = false;
			foreach ( $files as $i => $file ) {
				if ( is_file( $file ) ) {
					$has_files = true;
					break;
				} else {
					if ( $get_import_root( $file ) ) {
						$has_files = true;
						break;
					} else {
						unset( $files[ $i ] );
					}
				}
			}
			if ( ! $has_files ) {
				return false;
			}

			// Rekey the array incase anything was removed.
			$files = array_values( $files );

			if ( 1 === count( $files ) && is_dir( $files[0] ) ) {
				return $get_import_root( $files[0] );
			}

			return $path;
		};

		$get_root_relative_path = function( $path ) use( $root ) {
			$root_offset = strlen( $root );
			if ( '/' !== $root ) {
				$root_offset += 1;
			}

			return substr( $path, $root_offset );
		};

		array_walk( $directories, function( &$data, $path ) use( $root, $cwd_relative, $get_import_root, $get_root_relative_path ) {
			$import_root = $get_import_root( $path );
			if ( ! $import_root ) {
				// Unreadable, etc.
				$data = false;
				return;
			}

			$data = [
				'text' => substr(
						$get_root_relative_path( $import_root ),
						strlen( $cwd_relative )
					) . '/',
				'path' => $get_root_relative_path( $import_root )
			];

			$data['text'] = ltrim( $data['text'], '/' );
		} );

		$directories = array_filter( $directories );

		// Sort the directories case insensitively.
		uasort( $directories, $sort_by_text );

		// Prefix the parent directory.
		if ( str_starts_with( dirname( $cwd ), $root ) && dirname( $cwd ) != $cwd ) {
			$directories = array_merge(
				[
					dirname( $cwd ) => [
						'text' => __( 'Parent Folder', 'get-from-server' ),
						'path' => $get_root_relative_path( dirname( $cwd ) ) ?: '/',
					]
				],
				$directories
			);
		}

		$files = array_flip( array_filter( $nodes, function( $node ) {
			return is_file( $node );
		} ) );
		array_walk( $files, function( &$data, $path ) use( $root, $get_root_relative_path ) {
			$wp_filetype = wp_check_filetype( $path );
			$type = $wp_filetype['type'];
			$ext = $wp_filetype['ext'];
			
			// إضافة دعم لملفات إضافية في واجهة المستخدم
			if ( !$type ) {
				switch ( $ext ) {
					case 'iso':
						$type = 'application/x-iso9660-image';
						break;
					case 'zip':
						$type = 'application/zip';
						break;
					case 'rar':
						$type = 'application/x-rar-compressed';
						break;
					case '7z':
						$type = 'application/x-7z-compressed';
						break;
					case 'tar':
						$type = 'application/x-tar';
						break;
					case 'gz':
						$type = 'application/gzip';
						break;
					case 'bz2':
						$type = 'application/x-bzip2';
						break;
				}
			}
			
			$importable = ( false !== $type || current_user_can( 'unfiltered_upload' ) );
			$readable   = is_readable( $path );

			$data = [
				'text'       => basename( $path ),
				'file'       => $get_root_relative_path( $path ),
				'importable' => $importable,
				'readable'   => $readable,
				'error'      => (
					! $importable ? 'doesnt-meet-guidelines' : (
						! $readable ? 'unreadable' : false
					)
				),
			];
		} );

		// Sort case insensitively.
		uasort( $files, $sort_by_text );

		?>
		<div class="frmsvr_wrap">
			<form method="post" action="<?php echo esc_url( $url ); ?>">
				<p><?php
					printf(
						__( '<strong>Current Directory:</strong> %s', 'get-from-server' ),
						'<span id="cwd">' . $dirparts . '</span>'
					);
				?></p>

				<table class="widefat">
					<thead>
					<tr>
						<td class="check-column"><input type='checkbox'/></td>
						<td><?php _e( 'File', 'get-from-server' ); ?></td>
					</tr>
					</thead>
					<tbody>
					<?php

					foreach ( $directories as $dir ) {
						if ( ! $dir['path'] ) {
							continue;
						}

						printf(
							'<tr>
								<td>&nbsp;</td>
								<td><a href="%s">%s</a></td>
							</tr>',
							esc_url( add_query_arg( 'path', rawurlencode( $dir['path'] ), $url ) ),
							esc_html( $dir['text'] )
						);
					}

					$file_id = 0;
					foreach ( $files as $file ) {
						$error_str = '';
						if ( 'doesnt-meet-guidelines' === $file['error'] ) {
							$error_str = __( 'Sorry, this file type is not permitted for security reasons. If you need to import this file type, please contact your administrator.', 'get-from-server' );
						} else if ( 'unreadable' === $file['error'] ) {
							$error_str = __( 'Sorry, but this file is unreadable by your Webserver. Perhaps check your File Permissions?', 'get-from-server' );
						}

						printf(
							'<tr class="%1$s" title="%2$s">
								<th class="check-column">
									<input type="checkbox" id="file-%3$d" name="files[]" value="%4$s" %5$s />
								</th>
								<td><label for="file-%3$d">%6$s</label></td>
							</tr>',
							$file['error'] ?: '', // 1
							$error_str, // 2
							$file_id++, // 3
							$file['file'], // 4
							disabled( false, $file['readable'] && $file['importable'], false ), // 5
							esc_html( $file['text'] ) // 6
						);
					}

					// If we have any files that are error flagged, add the hidden row.
					if ( array_filter( array_column( $files, 'error' ) ) ) {
						printf(
							'<tr class="hidden-toggle">
								<td>&nbsp;</td>
								<td><a href="#">%1$s</a></td>
							</tr>',
							__( 'Show hidden files', 'get-from-server' )
						);
					}

					?>
					</tbody>
					<tfoot>
					<tr>
						<td class="check-column"><input type='checkbox'/></td>
						<td><?php _e( 'File', 'get-from-server' ); ?></td>
					</tr>
					</tfoot>
				</table>

				<br class="clear"/>
				<?php wp_nonce_field( 'gfs_import' ); ?>
				<?php submit_button( __( 'Import', 'get-from-server' ), 'primary', 'import', false ); ?>
			</form>
		</div>
	<?php
	}

	function language_notice( $force = false ) {
		$message_english = 'Hi there!
I notice you use WordPress in a Language other than English (US), Did you know you can translate WordPress Plugins into your native language as well?
If you\'d like to help out with translating this plugin into %1$s you can head over to <a href="%2$s">GitHub</a> and suggest translations for any languages which you know.
Thanks! Eng. A7meD KaMeL.';
		/* translators: %1$s = The Locale (de_DE, en_US, fr_FR, he_IL, etc). %2$s = The GitHub link to the plugin overview */
		$message = __( 'Hi there!
I notice you use WordPress in a Language other than English (US), Did you know you can translate WordPress Plugins into your native language as well?
If you\'d like to help out with translating this plugin into %1$s you can head over to <a href="%2$s">GitHub</a> and suggest translations for any languages which you know.
Thanks! Eng. A7meD KaMeL.', 'get-from-server' );

		$locale = get_locale();
		if ( function_exists( 'get_user_locale' ) ) {
			$locale = get_user_locale();
		}

		// Don't display the message for English (Any) or what we'll assume to be fully translated localised builds.
		if ( 'en_' === substr( $locale, 0, 3 ) || ( $message != $message_english && ! $force  ) ) {
			return false;
		}

		        $translate_url = 'https://github.com/Brmgina/get-from-server/';

		echo '<div class="notice notice-info"><p>' . sprintf( nl2br( $message ), get_locale(), $translate_url ) . '</p></div>';
	}

	function outdated_options_notice() {
		$old_root = get_option( 'frmsvr_root', '' );

		if (
			$old_root
			&&
			str_contains( $old_root, '%' )
			&&
			! defined( 'GET_FROM_SERVER' )
		) {
			printf(
				'<div class="notice error"><p>%s</p></div>',
				'You previously used the "Root Directory" option with a placeholder, such as "%username% or "%role%".<br>' .
				'Unfortunately this feature is no longer supported. As a result, Get From Server has been disabled for users who have restricted upload privledges.<br>' .
				'To make this warning go away, empty the "frmsvr_root" option on <a href="options.php#frmsvr_root">options.php</a>.'
			);
		}

		if ( $old_root && ! str_starts_with( $old_root, $this->get_root() ) ) {
			printf(
				'<div class="notice error"><p>%s</p></div>',
				'Warning: Root Directory changed. You previously used <code>' . esc_html( $old_root ) . '</code> as your "Root Directory", ' .
				'this has been changed to <code>' . esc_html( $this->get_root() ) . '</code>.<br>' .
				'To restore your previous settings, add the following line to your <code>wp-config.php</code> file:<br>' .
				'<code>define( "GET_FROM_SERVER", "' . $old_root . '" );</code><br>' .
				'To make this warning go away, empty the "frmsvr_root" option on <a href="options.php#frmsvr_root">options.php</a>.'
			);
		}
	}

	/**
	 * Check for plugin updates from GitHub
	 * 
	 * @param object $transient Update plugins transient
	 * @return object Modified transient
	 */
	function check_for_plugin_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// GitHub repository information
		$plugin_slug = basename( dirname( __FILE__ ) );
		$github_repo = 'Brmgina/get-from-server';
		$github_api_url = "https://api.github.com/repos/{$github_repo}/releases/latest";

		// Get latest release from GitHub
		$response = wp_remote_get( $github_api_url, array(
			'timeout' => 15,
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' )
			)
		) );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return $transient;
		}

		$release_data = json_decode( wp_remote_retrieve_body( $response ) );
		
		if ( ! $release_data || ! isset( $release_data->tag_name ) ) {
			return $transient;
		}

		// Remove 'v' prefix if present
		$latest_version = ltrim( $release_data->tag_name, 'v' );
		$current_version = VERSION;

		// Check if update is needed
		if ( version_compare( $latest_version, $current_version, '>' ) ) {
			$plugin_file = plugin_basename( __FILE__ );
			
			$transient->response[ $plugin_file ] = (object) array(
				'slug' => $plugin_slug,
				'new_version' => $latest_version,
				'url' => "https://github.com/{$github_repo}",
				'package' => $release_data->zipball_url,
				'requires' => '6.0',
				'requires_php' => '8.0',
				'tested' => '6.4',
				'last_updated' => $release_data->published_at,
				'sections' => array(
					'description' => $release_data->body,
					'changelog' => $release_data->body
				)
			);
		}

		return $transient;
	}

	/**
	 * Plugin API call for update information
	 * 
	 * @param bool|object $result Result object
	 * @param string $action Action being performed
	 * @param object $args Arguments
	 * @return object|bool Result object or false
	 */
	function plugin_api_call( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		$plugin_slug = basename( dirname( __FILE__ ) );
		
		if ( $args->slug !== $plugin_slug ) {
			return $result;
		}

		// GitHub repository information
		$github_repo = 'Brmgina/get-from-server';
		$github_api_url = "https://api.github.com/repos/{$github_repo}/releases/latest";

		$response = wp_remote_get( $github_api_url, array(
			'timeout' => 15,
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' )
			)
		) );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return $result;
		}

		$release_data = json_decode( wp_remote_retrieve_body( $response ) );
		
		if ( ! $release_data ) {
			return $result;
		}

		$latest_version = ltrim( $release_data->tag_name, 'v' );

		$result = (object) array(
			'name' => 'Get From Server',
			'slug' => $plugin_slug,
			'version' => $latest_version,
			'author' => 'Eng. A7meD KaMeL',
			'author_profile' => 'https://a-kamel.com/',
			'last_updated' => $release_data->published_at,
			'requires' => '6.0',
			'requires_php' => '8.0',
			'tested' => '6.4',
			'compatibility' => array(),
			'rating' => 0,
			'ratings' => array(),
			'num_ratings' => 0,
			'downloaded' => 0,
			'active_installs' => 0,
			'short_description' => 'Plugin to allow the Media Manager to get files from the webservers filesystem.',
			'sections' => array(
				'description' => $release_data->body,
				'installation' => '1. انسخ جميع ملفات الإضافة إلى مجلد `wp-content/plugins/get-from-server/`<br>2. فعّل الإضافة من لوحة التحكم<br>3. اذهب إلى Media → Get From Server',
				'changelog' => $release_data->body,
				'screenshots' => '',
				'faq' => '',
				'other_notes' => ''
			),
			'download_link' => $release_data->zipball_url,
			'homepage' => "https://github.com/{$github_repo}",
			'support' => "https://github.com/{$github_repo}/issues"
		);

		return $result;
	}

}
