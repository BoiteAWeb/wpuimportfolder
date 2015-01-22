<?php

/*
Plugin Name: Import Folder
Description: Import the content of a folder
Version: 0.3
Author: Darklg
Author URI: http://darklg.me/
Contributor : Juliobox
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

if ( is_admin() ) {

	class WPUImportFolder
	{
		private $options = array(
			'name' => '',
			'id' => 'wpuimportfolder',
		);

		private $extensions = array(
			'image' => array(
				'jpg',
				'jpeg',
				'png',
				'bmp',
				'gif'
			),
			'text' => array(
				'txt',
				'htm',
				'html'
			)
		);

		function __construct() {
			load_plugin_textdomain( $this->options['id'], false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
			$this->options['name'] = __( 'Import folder', 'wpuimportfolder' );
			add_action('init', array( &$this,
				'init'
			) );
		}

		function init() {

			$this->nonce_field = 'nonce_' . $this->options['id'] . '_import';
			$this->upload_dir = wp_upload_dir();
			$this->import_dir = $this->upload_dir['basedir'] . '/import/';

			// Set menu in settings
			add_action( 'admin_menu', array( &$this,
				'admin_menu'
			) );

			// Set admin post action
			add_action( 'admin_post_' . $this->options['id'], array( &$this,
				'admin_post_wpuimportfolder'
			) );

			// Display notices
			add_action( 'admin_notices', array( &$this,
				'admin_notices'
			) );
		}

		function admin_menu() {

			// Set page
			$page = add_management_page( $this->options['name'], $this->options['name'], 'manage_options', $this->options['id'], array( &$this,
				'admin_page'
			) );
			add_action( 'admin_footer-' . $page, array( &$this,
				'admin_page_script' 
			) );
		}

		function admin_page_script() {
			?>
			<script>
				jQuery( '#wpuimportfoldershowlist' ).on( 'click', function( e ) {
					e.preventDefault();
					jQuery( '#wpuimportfoldershowlistdiv' ).toggle( 'slow' );
				});
			</script>
			<?php
		}

		function admin_page() {
			$extensions = array_merge( $this->extensions['image'], $this->extensions['text'] );
			$files = file_exists( $this->import_dir ) ? glob( $this->import_dir . '*.{' . implode( ',', $extensions ) . '}', GLOB_BRACE ) : array();
			$has_files = is_array( $files ) && count( $files );
			$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );

			echo '<div class="wrap">';
			echo '<h2>' . $this->options['name'] . '</h2>';
			echo '<form action="' . admin_url( 'admin-post.php' ) . '" method="post">';
			if ( $has_files ) {
				echo '<p>';
					printf( _n( '%d file available.', '%d files available.', count( $files ), 'wpuimportfolder' ) , count( $files ) );
					echo ' <a href="#" id="wpuimportfoldershowlist" class="hide-if-no-js" title="' . esc_attr__( 'Modify selection', 'wpuimportfolder' ) . '">' . esc_html__( 'Modify selection', 'wpuimportfolder' ) . '</a>';
					echo '<div id="wpuimportfoldershowlistdiv" class="hide-if-js">';
						foreach ( $files as $file ) {
							echo '<label><input type="checkbox" checked name="checked_file[]" value="' . $file . '"> ' . $file . '<br>';
						}
					echo '</div>';
				echo '</p>';
				echo '<input type="hidden" name="action" value="wpuimportfolder">';
				wp_nonce_field( 'nonce_' . $this->options['id'], $this->nonce_field );

				// - Choose a post type
				echo '<p><label for="import_post_type"><b>' . __( 'Post type', 'wpuimportfolder' ) . '</b></label><br/>';
				if ( count( $post_types ) > 8 ) {
					echo '<select name="import_post_type" id="import_post_type" required>';
					echo '<option value="" disabled selected style="display:none;">' . __( 'Select a post type', 'wpuimportfolder' ) . '</option>';

					foreach ( $post_types as $id => $post_type ) {
						echo '<option value="' . $id . '">' . $post_type->labels->name . '</option>';
					}
					echo '</select>';
				} else {
					foreach ( $post_types as $id => $post_type ) {
						echo '<input type="radio" name="import_post_type" id="import_post_type" required value="' . $id . '"> ' . $post_type->labels->name . '<br>';
					}
				}
				echo '</p>';

				submit_button( __( 'Import selected files', 'wpuimportfolder' ) );
				echo '</form>';
			} else {
				echo '<p>';
					_e( 'No files to process.', 'wpuimportfolder' );
					echo ' <a href="' . admin_url( 'tools.php?page=' . $this->options['id'] ) . '">' . __( 'Reload', 'wpuimportfolder' ) . '</a>';
				echo '</p>';
			}
			echo '</div>';
		}

		function admin_post_wpuimportfolder() {

			$post_types = get_post_types( array( 'show_ui' => true ) );

			// Check nonce
			if ( ! isset( $_POST[ $this->nonce_field ] ) || ! wp_verify_nonce( $_POST[ $this->nonce_field ], 'nonce_' . $this->options['id'] ) ) {
				wp_nonce_ays( '' );
			}

			// Check post type
			if ( ! isset( $_POST['import_post_type'] ) || ! in_array( $_POST['import_post_type'], $post_types ) ) {
				wp_die( __( 'Unknown post type.', 'wpuimportfolder' ) ); // No domain = default domain (WordPress already know this string)
			}


			// Ensure the folder exists
			defined( 'FS_CHMOD_DIR' ) or define( 'FS_CHMOD_DIR', 0755 );
			if ( ! is_dir( $this->import_dir ) && ! mkdir( $this->import_dir, FS_CHMOD_DIR ) ) {
				wp_die( __( 'Error creating the import folder.', 'wpuimportfolder' ) );
			}

			$extensions = array_merge( $this->extensions['image'], $this->extensions['text'] );
			$files = glob( $this->import_dir . '*.{' . implode( ',', $extensions ) . '}', GLOB_BRACE );
			// Check post type
			if ( ! isset( $_POST['checked_file'] ) || false === ( $files = array_intersect( $files, array_map( 'stripslashes',$_POST['checked_file'] ) ) ) ) {
				wp_die( __( 'No files to process.', 'wpuimportfolder' ) ); 
			}

			// For each found file
			foreach ( $files as $file ) {
				$this->create_post_from_file( $file, $_POST['import_post_type'] );
			}

			wp_safe_redirect( wp_get_referer() );
			die();
		}

		/* ----------------------------------------------------------
		  Files tools
		---------------------------------------------------------- */

		/**
		 * Create post from a file
		 * @param  string   $file  Path of the file
		 * @return boolean		 Success creation
		 */
		private function create_post_from_file( $filepath, $post_type ) {
			// Extract path & title
			$filename = pathinfo( $filepath, PATHINFO_FILENAME );
			$extension = pathinfo( $filepath, PATHINFO_EXTENSION );
			$filetitle = $this->get_title_from_filename( basename( $filepath, '.' . $extension ) );

			$insert_post = array(
				'post_title' => $filetitle,
				'post_content' => '',
				'post_type' => $post_type,
				'post_status' => 'publish'
			);


			if ( in_array( $extension, $this->extensions['image'] ) ) {

				$type = wp_check_filetype( $filepath );
				$type = $type['type'];

				if ( preg_match( '#^image#', $type ) ) {

					$size = filesize( $filepath );
					$file_array = array(
						'name'		=> strtolower( remove_accents( basename( $filepath ) ) ),
						'tmp_name'	=> $filepath,
						'type'		=> $type,
						'size'		=> $size,
						'error'		=> UPLOAD_ERR_OK,
					);

					// "upload" file
					$file = wp_handle_sideload( $file_array, array( 'test_form' => false ), current_time( 'mysql' ) );
					if ( isset( $file['error'] ) ) {
						return false;
					}

					$url = $file['url'];
					$type = $file['type'];
					$file = $file['file'];
					$title = preg_replace('/\.[^.]+$/', '', basename( $file ) );
					$content = '';					

					// Finally insert the post into the database
					$post_id = wp_insert_post( $insert_post );

					if ( ! is_wp_error( $post_id ) ) {
					
						// Construct the attachment array
						$attachment = array(
							'post_mime_type' => $type,
							'guid' => $url,
							'post_parent' => $post_id,
							'post_title' => $title,
							'post_content' => $content,
						);

						// Save the attachment metadata
						$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
						if ( ! is_wp_error( $attach_id ) ) {
							wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $file ) );
						}

					 	// Set as featured image
					 	set_post_thumbnail( $post_id, $attach_id );
						
						// Add this post into the feedback message in admin_notices
						$this->set_message( $post_id );

					}

				}					

			}

			if ( in_array( $extension, $this->extensions['text'] ) ) {

				$post_content = file_get_contents( $filepath );
				$post_content = $extension == 'txt' ? wpautop( $post_content ) : $post_content;
				$insert_post['post_content'] = $post_content;

				// Finally insert the post into the database
				$post_exists = get_page_by_title( $filetitle, OBJECT, $post_type );
				if ( is_a( $post_exists, 'WP_Post' ) ) {
					$insert_post['ID'] = $post_exists->ID;
				}
				$post_id = wp_insert_post( $insert_post );
				if ( ! is_wp_error( $post_id ) ) {
					// Add this post into the feedback message in admin_notices
					$this->set_message( $post_id, isset( $insert_post['ID'] ) );
				}
			}

			// Delete old file
			@unlink( $filepath );

			return true;

		}

		/**
		 * Generate a title from a file name.
		 * @param  string $file Original file name
		 * @return string Generated title
		 */
		private function get_title_from_filename( $filename ) {
			// Remove unwanted characters
			return str_replace( array(
				'_',
				'-',
				'.'
			) , ' ', $filename );
		}

		/* ----------------------------------------------------------
		  WordPress tools
		---------------------------------------------------------- */

		/* Set notices messages */
		private function set_message( $post_id, $update = false ) {
			$ids = (array) get_transient( $GLOBALS['current_user']->ID . 'wpuimportfolder' );
			$group = $update ? 'updated' : 'created';
			$ids[ $group ][] = $post_id;
			set_transient( $GLOBALS['current_user']->ID . 'wpuimportfolder', $ids );
		}


		/* Display notices */
		function admin_notices() {
			global $current_user;
			$messages = array_filter( (array) get_transient( $current_user->ID . 'wpuimportfolder' ) );
			if ( current_user_can( 'manage_options' ) && ! empty( $messages ) ) {
				echo '<div class="updated">';
					if ( isset( $messages['created'] ) && count( array_filter( $messages['created'] ) ) ) {
						echo '<p>';
							echo '<b>' . 
								sprintf( _n( '%d post created.', '%d posts created.', count( $messages['created'] ), 'wpuimportfolder' ), count( $messages['created'] ) ) . 
								'</b><br>';
							echo '<ul class="ul-disc">';
							foreach ( $messages['created'] as $id ) {
								echo '<li>' . get_the_title( $id ) . ' ';
									echo '<a href="' . get_edit_post_link( $id ) .'">' . __( 'Edit' ) . '</a>';
									echo ' | ';
									echo '<a href="' . get_permalink( $id ) .'" target="_blank">' . __( 'View' ) . '</a>';
								echo '</li>';
							} 
							echo '</ul>';
						echo '</p>';
					}
					if ( isset( $messages['updated'] ) && count( array_filter( $messages['updated'] ) ) ) {
						echo '<p>';
							echo '<b>' . 
								sprintf( _n( '%d post updated.', '%d posts updated.', count( $messages['updated'] ), 'wpuimportfolder' ), count( $messages['updated'] ) ) . 
								'</b><br>';
							echo '<ul class="ul-disc">';
							foreach ( $messages['updated'] as $id ) {
								echo '<li>' . get_the_title( $id ) . ' ';
									echo '<a href="' . get_edit_post_link( $id ) .'">' . __( 'Edit' ) . '</a>';
									echo ' | ';
									echo '<a href="' . get_permalink( $id ) .'" target="_blank">' . __( 'View' ) . '</a>';
								echo '</li>';
							} 
							echo '</ul>';
						echo '</p>';
					}
				echo '</div>';
				delete_transient( $current_user->ID . 'wpuimportfolder' );
			}
		}
	}

	$WPUImportFolder = new WPUImportFolder();
}
