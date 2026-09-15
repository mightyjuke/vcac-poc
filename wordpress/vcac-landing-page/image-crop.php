<?php

namespace VCAC\LandingPage;

defined('ABSPATH') || exit;

function sanitize_card_position($value) {
    return is_scalar($value) && is_numeric($value) && is_finite((float) $value)
        ? max(0, min(100, (int) round((float) $value))) : 50;
}

function card_image_settings($post_id) {
    return array(
        'imageFit' => 'contain' === get_post_meta($post_id, '_vcac_image_fit', true) ? 'contain' : 'cover',
        'imagePositionX' => sanitize_card_position(get_post_meta($post_id, '_vcac_image_x', true)),
        'imagePositionY' => sanitize_card_position(get_post_meta($post_id, '_vcac_image_y', true)),
    );
}

add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if (!$screen || 'post' !== $screen->base || !in_array($screen->post_type, array('post', 'mec-events'), true)) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_style('vcac-card-crop', plugins_url('admin-crop.css', __FILE__), array(), FEED_VERSION);
    wp_enqueue_script('vcac-card-crop', plugins_url('admin-crop.js', __FILE__), array('media-views'), FEED_VERSION, true);
});

function render_card_image_settings($post) {
    $settings = card_image_settings($post->ID);
    $thumbnail_id = get_post_thumbnail_id($post->ID);
    $image = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'large') : '';
    ?>
    <fieldset class="vcac-card-crop" data-thumbnail-id="<?php echo esc_attr($thumbnail_id); ?>">
        <legend><strong>Landing-page card image</strong></legend>
        <p>Uses this event or post's featured image. Adjust the 16:9 card preview below, then save or update the post. The original image and ministry page are unchanged.</p>
        <p><label for="vcac-image-fit">Image display</label><br>
            <select id="vcac-image-fit" name="vcac_image_fit">
                <option value="cover"<?php selected($settings['imageFit'], 'cover'); ?>>Fill card (crop edges)</option>
                <option value="contain"<?php selected($settings['imageFit'], 'contain'); ?>>Show full image (best for posters)</option>
            </select></p>
        <div class="vcac-crop-preview" role="img" aria-label="Preview of the landing-page card image">
            <img <?php if ($image) : ?>src="<?php echo esc_url($image); ?>"<?php else : ?>hidden<?php endif; ?> alt="" style="object-fit:<?php echo esc_attr($settings['imageFit']); ?>;object-position:<?php echo esc_attr($settings['imagePositionX']); ?>% <?php echo esc_attr($settings['imagePositionY']); ?>%">
        </div>
        <p class="vcac-crop-message" role="status"><?php echo $image ? 'Adjust the sliders to keep the important part visible.' : 'Choose a featured image to see its crop here.'; ?></p>
        <div class="vcac-crop-controls">
            <?php foreach (array('x' => 'Horizontal position (left to right)', 'y' => 'Vertical position (top to bottom)') as $axis => $label) : ?>
                <p><label for="vcac-image-<?php echo esc_attr($axis); ?>"><?php echo esc_html($label); ?></label><br>
                    <input type="range" min="0" max="100" step="1" id="vcac-image-<?php echo esc_attr($axis); ?>" name="vcac_image_<?php echo esc_attr($axis); ?>" value="<?php echo esc_attr($settings['x' === $axis ? 'imagePositionX' : 'imagePositionY']); ?>">
                    <output for="vcac-image-<?php echo esc_attr($axis); ?>"></output></p>
            <?php endforeach; ?>
            <button type="button" class="button vcac-crop-reset">Centre crop</button>
        </div>
        <p class="description">Only the direction with cropped edges will move. Use “Show full image” if a poster's text gets cut off.</p>
    </fieldset>
    <?php
}

add_action('save_post', function ($post_id, $post) {
    if (!in_array($post->post_type, array('post', 'mec-events'), true)
        || !isset($_POST['vcac_content_listing_nonce'], $_POST['vcac_image_fit'])
        || !is_scalar($_POST['vcac_content_listing_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vcac_content_listing_nonce'])), 'vcac_save_content_listing')
        || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)
        || !current_user_can('edit_post', $post_id)) {
        return;
    }
    update_post_meta($post_id, '_vcac_image_fit', 'contain' === $_POST['vcac_image_fit'] ? 'contain' : 'cover');
    foreach (array('x', 'y') as $axis) {
        if (isset($_POST['vcac_image_' . $axis])) {
            update_post_meta($post_id, '_vcac_image_' . $axis, sanitize_card_position(wp_unslash($_POST['vcac_image_' . $axis])));
        }
    }
}, 10, 2);
