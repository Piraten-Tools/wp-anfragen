<?php
/*
	Plugin Name: Anfragen
	Plugin URI: https://github.com/zutrinken/anfragen
	Description: Plugin um parlamentarische Anfragen zu dokumentieren
	Version: 0.9
	Author: Peter Amende
	Author URI: http://zutrinken.com
	Text Domain: anfragen
	Domain Path: /languages
	License: GPLv2 or later
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

load_plugin_textdomain('anfragen', false, basename( dirname( __FILE__ ) ) . '/languages');

add_action( 'init', 'create_anfragen_post_type' );
function create_anfragen_post_type() {
	register_post_type(
		'anfragen',
		array(
			'labels' => array(
				'name' => __( 'Requests','anfragen' ),
				'singular_name' => __( 'Request','anfragen' )
			),
			'public' => true,
			'has_archive' => true,
			'rewrite' => array(
				'slug' => 'anfragen',
				'with_front' => FALSE
			),
			'supports' => array( 'title', 'editor', 'custom-fields', 'revisions', 'my-meta-box' ),
			'register_meta_box_cb' => 'add_anfragen_meta_box'
		)
	);
	flush_rewrite_rules();
}

function add_anfragen_meta_box( $post ) {
    add_meta_box( 
        'anfragen',
        __('Request reply date','anfragen'),
        'anfragen_meta_box',
        'anfragen',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'add_anfragen_meta_box');

function add_anfragen_custom_scripts() {
	wp_enqueue_script('jquery-ui-datepicker');
	wp_enqueue_script('anfragen-script', plugins_url( 'anfragen.js' , __FILE__ ), array(), true, true );
}
add_action('admin_head','add_anfragen_custom_scripts');

function anfragen_meta_box($object, $box) {
	global $post;
	wp_nonce_field( basename( __FILE__ ), 'anfragen_meta_box_nonce' );
	
	?>
		<input class="widefat" type="text" name="anfragen_response_date_field" id="anfragen_response_date_field" value="<?php echo esc_attr( get_post_meta( $object->ID, 'anfragen_response_date_field', true ) ); ?>" size="30" autocomplete="off" />
	<?php
}

function save_anfragen_meta_box($post_id, $post) {
	/* Verify the nonce before proceeding. */
	if ( !isset( $_POST['anfragen_meta_box_nonce'] ) || !wp_verify_nonce( $_POST['anfragen_meta_box_nonce'], basename( __FILE__ ) ) )
		return $post_id;

	/* Get the post type object. */
	$post_type = get_post_type_object( $post->post_type );

	/* Check if the current user has permission to edit the post. */
	if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
		return $post_id;

	/* Get the posted data and sanitize it for use as an HTML class. */
	$new_meta_value = ( isset( $_POST['anfragen_response_date_field'] ) ? sanitize_html_class( $_POST['anfragen_response_date_field'] ) : '' );

	/* Get the meta key. */
	$meta_key = 'anfragen_response_date_field';

	/* Get the meta value of the custom field key. */
	$meta_value = get_post_meta( $post_id, $meta_key, true );
		
	/* If a new meta value was added and there was no previous value, add it. */
	if ( $new_meta_value && '' == $meta_value )
		add_post_meta( $post_id, $meta_key, $new_meta_value, true );

	/* If the new meta value does not match the old value, update it. */
	elseif ( $new_meta_value && $new_meta_value != $meta_value )
		update_post_meta( $post_id, $meta_key, $new_meta_value );

	/* If there is no new meta value but an old value exists, delete it. */
	elseif ( '' == $new_meta_value && $meta_value )
		delete_post_meta( $post_id, $meta_key, $meta_value );
}
add_action('save_post', 'save_anfragen_meta_box', 10, 2);

function display_anfragen_shortcode($atts) {

	$args = array(
		'post_type' => 'anfragen',
		'order' => $order,
		'orderby' => $orderby,
		'showposts' => '200',
	);

	$return = '';
	

	$listing = new WP_Query($args);
	if ( $listing->have_posts() ):

		$return .= '<div id="anfragen">';

		while ( $listing->have_posts() ): $listing->the_post(); global $post;

			$output = '';

			$anfragen_meta = anfragen_build_meta();
			
			$output .= '<hr />';
			$output .= '<h4><a href="'. get_permalink() .'">'. get_the_title() . '</a></h4>';
			$output .= $anfragen_meta;
			
			$return .= apply_filters( 'display_anfragen_shortcode', $output);

		endwhile;
		
		$return .= '</div>';

	endif; wp_reset_query();

	if (!empty($return)) return $return;
}
add_shortcode('anfragen', 'display_anfragen_shortcode');


function anfragen_build_meta() {
	$date_today = date('U');
	$anfragen_meta = '';
	
	$date_done = get_post_meta( get_the_ID(), 'anfragen_response_date_field', true );
	
	$date_create = get_the_date('U');

	if($date_done > 1) {
		list($day, $month, $year) = explode('-', $date_done);
		$date_done = $day .'.'. $month .'.'. $year;
		
		$dt = DateTime::createFromFormat('d.m.Y', $date_done);
		$timestamp = $dt->format('U');

		if($timestamp > $date_create) {
			$time = abs(floor(($date_create - $timestamp) / (24*60*60)));
			if($time <= 14) {
				$anfrage_status = '';
				$anfrage_status .= '<strong>' . __('Done','anfragen') . '</strong>';
				$anfrage_status .= ' ';
				$anfrage_status .= '<i class="anfragen-green">';
				$anfrage_status .= sprintf(
					_n(
						'after one day',
						'after %s days',
						$time,
						'anfragen'
					), $time
				);
				$anfrage_status .= '</i>';
			} else {
				$anfrage_status = '';
				$anfrage_status .= '<strong>' . __('Done','anfragen') . '</strong>';
				$anfrage_status .= ' ';
				$anfrage_status .= '<i class="anfragen-orange">';
				$anfrage_status .= sprintf(
					_n(
						'after one day',
						'after %s days',
						$time,
						'anfragen'
					), $time
				);
				$anfrage_status .= '</i>';
			}
		}
		
	} else {
		$time = floor((($date_create + 14*24*60*60) - $date_today) / (24*60*60));
		if ($time <= -1) {
			$time = abs($time - 14);
			$anfrage_status = '';
			$anfrage_status .= '<strong>' . __('Open','anfragen') . '</strong>';
			$anfrage_status .= ' ';
			$anfrage_status .= '<i class="anfragen-red">';
			$anfrage_status .= sprintf(
				_n(
					'since one day',
					'since %s days',
					$time,
					'anfragen'
				), $time
			);
			$anfrage_status .= '</i>';
		} else {
			$time = abs($time - 14);
			$anfrage_status = '';
			$anfrage_status .= '<strong>' . __('Open','anfragen') . '</strong>';
			$anfrage_status .= ' ';
			$anfrage_status .= '<i class="anfragen-blue">';
			$anfrage_status .= sprintf(
				_n(
					'since one day',
					'since %s days',
					$time,
					'anfragen'
				), $time
			);
			$anfrage_status .= '</i>';
		}
	}
	
	
	$anfragen_meta .= '<span class="anfragen-meta"><span class="anfragen-label">' . __('Date','anfragen') . ':</span><span class="anfragen-value">'. get_the_date('d.m.Y') .'</span></span>';
	if($date_done > 1) {
		$anfragen_meta .= '<span class="anfragen-meta"><span class="anfragen-label">' . __('Reply','anfragen') . ':</span><span class="anfragen-value">' . $date_done . '</span></span>';
	}
	$anfragen_meta .= '<span class="anfragen-meta"><span class="anfragen-label">' . __('Status','anfragen') . ':</span><span class="anfragen-value">' . $anfrage_status . '</span></span>';
	
	return $anfragen_meta;
}



function anfragen_extend_content($content){
	global $post;
	if ($post->post_type == 'anfragen') {
		$custom_content = anfragen_build_meta();
		$custom_content .= '<hr />';
		$custom_content .= $content;
		return $custom_content;
	} else {
		return $content;
	}
}
add_filter('the_content', 'anfragen_extend_content');




function anfragen_add_styles() {
	wp_enqueue_style( 'anfragen-css', plugins_url('anfragen.css', __FILE__), array());
}
add_action( 'wp_print_styles', 'anfragen_add_styles' );

if(!function_exists('_log')){
  function _log( $message ) {
    if( WP_DEBUG === true ){
      if( is_array( $message ) || is_object( $message ) ){
        error_log( print_r( $message, true ) );
      } else {
        error_log( $message );
      }
    }
  }
}
?>