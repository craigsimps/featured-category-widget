<?php
/**
 * Featured Category Widget
 *
 * Showcase a specific category with ease, including setting a featured image and listing child categories.
 *
 * @package   Featured_Category_Widget
 * @author    Craig Simpson <craig@craigsimpson.scot>
 * @license   GPL2
 * @link      https://craigsimpson.scot
 * @copyright 2018 Craig Simpson
 *
 * @wordpress-plugin
 * Plugin Name:       Featured Category Widget
 * Plugin URI:        https://craigsimpson.scot/featured-category-widget
 * Description:       Showcase a specific category with ease, including setting a featured image and optionally listing child categories.
 * Version:           1.0.1
 * Author:            Craig Simpson
 * Author URI:        https://craigsimpson.scot/
 * License:           GPL2
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       featured-category-widget
 * Domain Path:       /languages
 *
 *
 * Featured Category Widget is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Featured Category Widget is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Featured Category Widget. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 */

defined( 'ABSPATH' ) || exit;

define( 'FEATURED_CATEGORY_WIDGET_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'FEATURED_CATEGORY_WIDGET_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );

/**
 * Load plugin textdomain.
 *
 * @since 1.0.0
 */
load_plugin_textdomain( 'featured-category-widget', false, FEATURED_CATEGORY_WIDGET_DIR . 'languages' );

/**
 * Class Featured_Category_Widget
 *
 * @package Featured_Category_Widget
 * @since 1.0.0
 */
class Featured_Category_Widget extends WP_Widget {

	/**
	 * Holds the widget slug.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Holds widget settings defaults, populated in constructor.
	 *
	 * @var array
	 */
	protected $defaults;

	/**
	 * Featured_Category_Widget constructor.
	 *
	 * @access public
	 */
	public function __construct() {

		$this->slug = 'featured-category-widget';

		$this->defaults = [
			'title'            => '',
			'image_uri'        => '',
			'image_id'         => '',
			'image_size'       => 'medium',
			'taxonomy'         => '',
			'term'             => '',
			'show_description' => false,
			'show_children'    => false,
		];

		$widget_options = [
			'classname'   => 'featured-category',
			'description' => 'Showcase a specific category with easy, including setting a featured image and listing child categories.',
		];

		parent::__construct( 'Featured_Category_Widget', 'Featured Category Widget', $widget_options );

		// Enqueue widget assets and enable AJAX.
		add_action( 'admin_enqueue_scripts', [ $this, 'load_admin_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'load_public_assets' ] );
		add_action( 'wp_ajax_get_terms', [ $this, 'get_terms' ] );

		// Deleted cached featured category widgets when categories change.
		add_action( 'create_category', [ $this, 'flush_widget_cache' ] );
		add_action( 'delete_category', [ $this, 'flush_widget_cache' ] );
		add_action( 'edit_category', [ $this, 'flush_widget_cache' ] );
	}

	/**
	 * Load admin JS and CSS.
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function load_admin_assets() {
		wp_enqueue_media();
		wp_enqueue_script( $this->slug, FEATURED_CATEGORY_WIDGET_URL . 'assets/admin/featured-category-widget.js', [ 'jquery', 'media-upload' ], '1.0.0', true );
		wp_enqueue_style( $this->slug, FEATURED_CATEGORY_WIDGET_URL . 'assets/admin/featured-category-widget.css', [], '1.0.0' );
	}

	/**
	 * Load public CSS.
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function load_public_assets() {
		if ( false === apply_filters( 'featured_category_widget_load_public_assets', true ) ) {
			return;
		}

		wp_enqueue_style( $this->slug, FEATURED_CATEGORY_WIDGET_URL . 'assets/public/featured-category-widget.css' );
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @param array $args Widget arguments.
	 * @param array $instance Widget instance.
	 *
	 * @return string Widget HTML output.
	 */
	public function widget( $args, $instance ) {

		$instance = wp_parse_args( (array) $instance, $this->defaults );
		$cache    = wp_cache_get( $this->slug, 'widget' );

		if ( ! isset ( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		if ( isset ( $cache[ $args['widget_id'] ] ) ) {
			return print $cache[ $args['widget_id'] ];
		}

		if ( ! is_array( $cache ) ) {
			$cache = [];
		}

		$widget = $args['before_widget'];

		if ( $instance['title'] ) {
			$widget .= $args['before_title'] . $instance['title'] . $args['after_title'];
		}

		ob_start();

		// Get Details of Featured Term.
		$featured_term      = get_term( $instance['term'], $instance['taxonomy'] );
		$featured_term_link = get_term_link( (int) $instance['term'], $instance['taxonomy'] );

		// Get Featured Term Image.
		$image_id   = apply_filters( 'featured_category_widget_image_id', $instance['image_id'], $instance, $this->id );
		$image_size = apply_filters( 'featured_category_widget_image_size', $instance['image_size'], $instance, $this->id );
		$image_args = apply_filters( 'featured_category_widget_image_args', [ 'class' => 'featured-category-image' ], $instance, $this->id );
		$image      = wp_get_attachment_image( $image_id, $image_size, false, $image_args );

		if ( '' !== $image ) {
			do_action( 'featured_category_widget_before_image', $instance, $this->id );
			printf(
				// Output the image, and surround it with a link to the featured category.
				'<a href="%1$s" class="featured-category-image-link">%2$s</a>',
				esc_url( apply_filters( 'featured_category_widget_title_link', $featured_term_link, $instance, $this->id ) ),
				wp_kses_post( $image )
			);
			do_action( 'featured_category_widget_after_image', $instance, $this->id );
		}

		do_action( 'featured_category_widget_before_category_title', $instance, $this->id );
		printf(
		    // Output the featured category.
			'<%1$s class="%2$s"><a href="%3$s">%4$s</a></%1$s>',
			esc_html( apply_filters( 'featured_category_widget_title_tag', 'h4', $instance, $this->id ) ),
			esc_attr( apply_filters( 'featured_category_widget_title_class', 'featured-category-title', $instance, $this->id ) ),
			esc_url( apply_filters( 'featured_category_widget_title_link', $featured_term_link, $instance, $this->id ) ),
			esc_html( apply_filters( 'featured_category_widget_title_text', $featured_term->name, $instance, $this->id ) )
		);
		do_action( 'featured_category_widget_after_category_title', $instance, $this->id );

		if ( $instance['show_description' ] && ! empty( $featured_term->description ) ) {
			do_action( 'featured_category_widget_before_category_description', $instance, $this->id );
			echo '<div class="featured-category-description">';
			echo wp_kses_post( wpautop( apply_filters( 'featured_category_widget_title_description', $featured_term->description, $instance, $this->id ) ) );
			echo '</div>';
			do_action( 'featured_category_widget_after_category_description', $instance, $this->id );
		}

		// Check if the "Show Subcategories" box is checked and then look up subcategories.
		$featured_category_child_terms = $instance['show_children'] ? get_term_children( (int) $instance['term'], $instance['taxonomy'] ) : null;

		// If we have an array of child terms, and there is at least 1.
		if ( is_array( $featured_category_child_terms ) && 0 < count( $featured_category_child_terms ) ) {
			do_action( 'featured_category_widget_before_child_terms', $instance, $this->id );
			echo '<ul class="featured-category-child-terms">';
			foreach ( $featured_category_child_terms as $i => $child_term_id ) {
				// Loop through each subcategory.
				$child_term      = get_term( $child_term_id, $instance['taxonomy'] );
				$child_term_link = get_term_link( $child_term->term_id, $instance['taxonomy'] );
				$child_term_name = $child_term->name;
				printf(
				// Output each subcategory as a list item, linked to its individual category.
					'<li class="featured-category-child-term"><a href="%1$s">%2$s</a></li>',
					esc_url( apply_filters( "featured_category_child_term_{$i}_link", $child_term_link, $instance, $this->id ) ),
					esc_html( apply_filters( "featured_category_child_term_{$i}_title", $child_term_name, $instance, $this->id ) )
				);
			}
			echo '</ul>';
			do_action( 'featured_category_widget_after_child_terms', $instance, $this->id );
		}

		$widget .= ob_get_clean();
		$widget .= $args['after_widget'];

		// Add this instance of the widget to our cache array.
		$cache[ $args['widget_id'] ] = $widget;

		// Save this instance of the widget to the object cache.
		wp_cache_set( $this->slug, $cache, 'widget' );

		return print $widget;
	}

	/**
	 * Outputs the options form on admin
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @param array $instance Widget instance.
	 *
	 * @return void
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->defaults ); ?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">
				<?php echo esc_html__( 'Title:', 'featured-category-widget' ); ?>
			</label><br/>
			<input type="text" name="<?php echo $this->get_field_name( 'title' ); ?>" id="<?php echo $this->get_field_id( 'title' ); ?>" value="<?php echo $instance['title']; ?>" class="widefat fc-title-input"/>
		</p>
		<p>
			<span class="fc-placeholder <?php echo esc_attr( $instance['image_uri'] ? 'u-hidden' : '' ); ?>">No image selected</span>
			<img src="<?php echo esc_url( $instance['image_uri'] ); ?>" class="fc-preview-img <?php echo esc_attr( ! $instance['image_uri'] ? 'u-hidden' : '' ); ?>"/>

			<input type="hidden" class="fc-image-uri" value="<?php echo $instance['image_uri']; ?>" name="<?php echo $this->get_field_name( 'image_uri' ); ?>"/>
			<input type="hidden" class="fc-image-id" name="<?php echo $this->get_field_name( 'image_id' ); ?>" id="<?php echo $this->get_field_id( 'image_id' ); ?>" value="<?php echo $instance['image_id']; ?>"/>
			<input type="button" class="button fc-select-img <?php echo esc_attr( $instance['image_id'] ? 'u-hidden' : '' ); ?>" name="<?php echo $this->get_field_name( 'image_id' ); ?>" value="<?php echo esc_html__( 'Add Image', 'featured-category-widget' ) ?>"/>
			<input type="button" class="button fc-remove-img <?php echo empty( $instance['image_id'] ) ? esc_attr( 'u-hidden' ) : null ?>" value="<?php echo esc_html__( 'Remove Image', 'featured-category-widget' ) ?>"/>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'image_size' ); ?>">
				<?php echo esc_html__( 'Image Size:', 'featured-category-widget' ); ?>
			</label><br>
			<select name="<?php echo $this->get_field_name( 'image_size' ); ?>" class="fc-image-size-selection widefat" id="<?php echo $this->get_field_id( 'image_size' ); ?>">
				<?php echo implode( '', $this->get_image_size_options( $instance['image_size'] ) ); ?>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'taxonomy' ); ?>">
				<?php echo esc_html__( 'Taxonomy:', 'featured-category-widget' ); ?>
			</label><br>
			<select name="<?php echo $this->get_field_name( 'taxonomy' ); ?>" class="fc-taxonomy-selection widefat" id="<?php echo $this->get_field_id( 'taxonomy' ); ?>">
				<?php echo implode( '', $this->get_taxonomies( $instance['taxonomy'] ) ); ?>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'term' ); ?>">
				<?php echo esc_html__( 'Category:', 'featured-category-widget' ); ?>
			</label><br>
			<select name="<?php echo $this->get_field_name( 'term' ); ?>" class="fc-term-selection widefat" id="<?php echo $this->get_field_id( 'term' ); ?>">
				<?php echo implode( '', $this->get_terms( false, $instance['taxonomy'], $instance['term'] ) ); ?>
			</select>
		</p>
		<p>
			<input type="checkbox" name="<?php echo $this->get_field_name( 'show_description' ); ?>" class="checkbox"<?php checked( $instance['show_description'] ); ?> id="<?php echo $this->get_field_id( 'show_description' ); ?>"/>
			<label for="<?php echo $this->get_field_id( 'show_description' ); ?>">
				<?php echo esc_html__( 'Show Category Description?', 'featured-category-widget' ); ?>
			</label>
		</p>
		<p>
			<input type="checkbox" name="<?php echo $this->get_field_name( 'show_children' ); ?>" class="checkbox"<?php checked( $instance['show_children'] ); ?> id="<?php echo $this->get_field_id( 'show_children' ); ?>"/>
			<label for="<?php echo $this->get_field_id( 'show_children' ); ?>">
				<?php echo esc_html__( 'Show Subcategories?', 'featured-category-widget' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * This function returns options for image output size.
	 *
	 * @access private
	 * @since  1.0.0
	 *
	 * @param string $current_size Existing image size.
	 *
	 * @return array Image sizes as <option> elements for <select> dropdown.
	 */
	private function get_image_size_options( $current_size ) {

		$ignored_image_sizes = apply_filters( 'featured_category_ignored_image_sizes', [], $this->id );
		$image_size_options  = [];

		foreach ( get_intermediate_image_sizes() as $size ) {
			if ( ! in_array( $size, $ignored_image_sizes, true ) ) {
				$image_size_options[] = sprintf(
					'<option %1$s value="%2$s">%2$s (%3$sx%4$s)</option>',
					$size === $current_size ? 'selected' : '',
					esc_attr( $size ),
					esc_attr( get_option( "{$size}_size_w" ) ),
					esc_attr( get_option( "{$size}_size_h" ) )
				);
			}
		}

		return $image_size_options;
	}

	/**
	 * Function used to return all taxonomies as an array.
	 *
	 * @access private
	 * @since  1.0.0
	 *
	 * @param string $current Current taxonomy name.
	 *
	 * @return array used in the taxonomy selection box.
	 */
	private function get_taxonomies( $current ) {

		$ignored_taxonomies = apply_filters( 'featured_category_ignored_taxonomies', [
			'post_tag',
			'nav_menu',
			'link_category',
			'post_format',
		], $this->id );

		$taxonomy_options = [
			'<option>' . __( 'Select Taxonomy', 'featured-category-widget' ) . '</option>',
		];

		foreach ( get_taxonomies() as $taxonomy ) {
			if ( ! in_array( $taxonomy, $ignored_taxonomies, true ) ) {
				$taxonomy_object = get_taxonomy( $taxonomy );
				$taxonomy_options[] = sprintf(
					'<option %1$s value="%2$s">%3$s</option>',
					$taxonomy === $current ? 'selected' : '',
					esc_attr( $taxonomy_object->name ),
					esc_attr( $taxonomy_object->labels->name )
				);
			}
		}

		return $taxonomy_options;
	}

	/**
	 * Function used by ajax to return all terms for the selected taxonomy.
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @param bool $ajax Should this function return as JSON.
	 * @param string $taxonomy Currently selected taxonomy.
	 * @param string $selected_term Currently selected term.
	 *
	 * @return mixed JSON or Array used in term selection box.
	 */
	public function get_terms( $ajax = true, $taxonomy = null, $selected_term = null ) {
		$selected_taxonomy = isset( $_POST['taxonomy'] ) ? $_POST['taxonomy'] : $taxonomy;
		$ajax              = isset( $_POST['action'] ) && $_POST['action'] === 'get_terms' ? true : $ajax;

		$term_options = [
			'<option>' . __( 'Select Category', 'featured-category-widget' ) . '</option>',
		];

		if ( null !== $selected_taxonomy ) {
			$terms = get_terms( [
				'taxonomy'   => $selected_taxonomy,
				'hide_empty' => false
			] );
			if ( ! $terms instanceof WP_Error ) {
				foreach ( $terms as $term ) {
					$term_options[] = sprintf(
						'<option %svalue="%s">%s</option>',
						( isset( $selected_term ) && $term->term_id === (int) $selected_term ) ? 'selected ' : '',
						esc_attr( $term->term_id ),
						esc_attr( $term->name )
					);
				}
			}
		}

		if ( $ajax ) {
			echo implode( $term_options, '' );
			wp_die();
		}

		return $term_options;
	}

	/**
	 * Delete the cached version of this widget.
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function flush_widget_cache() {
		return wp_cache_delete( $this->slug, 'widget' );
	}

	/**
	 * Processing widget options on save.
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @param array $new_instance The new instance.
	 * @param array $instance The previous instance.
	 *
	 * @return array of the options for the plugin.
	 */
	public function update( $new_instance, $instance ) {

		$instance['title']            = esc_html( $new_instance['title'] );
		$instance['image_uri']        = esc_url( $new_instance['image_uri'] );
		$instance['image_id']         = esc_attr( $new_instance['image_id'] );
		$instance['image_size']       = esc_attr( $new_instance['image_size'] );
		$instance['taxonomy']         = esc_attr( $new_instance['taxonomy'] );
		$instance['term']             = esc_attr( $new_instance['term'] );
		$instance['show_description'] = $new_instance['show_description'] ? true : false;
		$instance['show_children']    = $new_instance['show_children'] ? true : false;

		return $instance;
	}
}

add_action( 'widgets_init', 'load_featured_category_widget' );
/**
 * Register the Featured Category Widget
 *
 * @since 1.0.0
 */
function load_featured_category_widget() {
	register_widget( 'Featured_Category_Widget' );
}
