<?php
/**
 * این فایل مسئول ثبت شورت‌کدها و نمایش فرم و لیست سفارشات است.
 * @version 7.1.2
 */

if (!defined('ABSPATH')) {
    exit;
}


// --- هوک‌های اصلی ---
add_action('init', function() {
    add_shortcode('instagram_tools_form', 'it_render_form_wrapper');
    add_shortcode('it_my_orders_list', 'it_render_my_orders_list_shortcode');
});

// --- هوک‌های ایجکس ---
add_action('wp_ajax_it_get_brand_services', 'it_ajax_get_brand_services');
add_action('wp_ajax_nopriv_it_get_brand_services', 'it_ajax_get_brand_services');
add_action('wp_ajax_get_products_by_category', 'it_get_products_by_category');
add_action('wp_ajax_nopriv_get_products_by_category', 'it_get_products_by_category');
add_action('wp_ajax_it_get_order_details', 'it_ajax_get_order_details_html');
add_action('wp_ajax_it_load_more_orders', 'it_ajax_load_more_orders_html');

// غرفعال موقت ژاکت
//function it_render_form() {
    
    // $license_status = get_option('it_license_status', 'not_installed');
    //if ($license_status !== 'valid') {
        //$settings_url = admin_url('admin.php?page=social-panel-settings#tab-license');
        //return '<div class="it-error-box">لایسنس شما فعال نیست. لطفاً برای فعال‌سازی افزونه به <a href="' . esc_url($settings_url) . '">صفحه تنظیمات</a> مراجعه کنید.</div>';
    //}
    //function it_render_form() {
    // The license check is temporarily disabled.

    // غرفعال موقت ژاکت
/**
 * تابع اصلی برای رندر کردن شورت‌کد فرم
 * این تابع به عنوان یک Wrapper عمل کرده و بر اساس تنظیمات، فرم اصلی یا صفحه برندینگ را نمایش می‌دهد.
 */
function it_render_form_wrapper() {
    $license_status = 'valid'; // غیرفعال‌سازی موقت بررسی لایسنس
    if ($license_status !== 'valid') {
        $settings_url = admin_url('admin.php?page=social-panel-settings#tab-license');
        return '<div class="it-error-box">لایسنس شما فعال نیست. لطفاً برای فعال‌سازی افزونه به <a href="' . esc_url($settings_url) . '">صفحه تنظیمات</a> مراجعه کنید.</div>';
    }

    ob_start();

    // -- کد اصلاح شده از اینجا شروع می‌شود --
    $require_login = get_option('it_require_login', 1);

    // ابتدا بررسی می‌کنیم که آیا الزام به ورود فعال است یا خیر
    if ($require_login && !is_user_logged_in()) {
        // اگر الزام به ورود فعال بود و کاربر لاگین نبود
        if (isset($_GET['it_reset_key']) && isset($_GET['it_login'])) {
            it_render_reset_password_form();
        } else {
            it_render_auth_tabs();
        }
    } else {
        // اگر الزام به ورود غیرفعال بود یا کاربر لاگین بود
        if (is_user_logged_in() && get_user_meta(get_current_user_id(), '_it_needs_password_reset', true)) {
            it_render_set_password_form();
        } else {
            $is_brand_selection_enabled = get_option('it_brand_selection_enabled', 0);
            
            echo '<div id="it-dynamic-form-container">'; // کانتینر برای تعویض محتوا با ایجکس
            
            if ($is_brand_selection_enabled) {
                it_render_brand_selection_page();
            } else {
                it_render_main_order_form(); // نمایش مستقیم فرم
            }
            
            echo '</div>';
        }
    }
    // -- کد اصلاح شده در اینجا تمام می‌شود --


    $theme = get_option('it_device_theme', 'iphone');
    $mockup_class = ($theme === 'samsung') ? 'samsung-mockup' : 'iphone-mockup';
    $frame_class = ($theme === 'samsung') ? 'samsung-frame' : 'iphone-frame';
    $screen_class = ($theme === 'samsung') ? 'samsung-screen' : 'iphone-screen';
    $camera_element = ($theme === 'samsung') ? '<div class="punch-hole-camera"></div>' : '<div class="dynamic-island"></div>';

    $output = ob_get_clean();
    
    return sprintf(
        '<div class="%s"><div class="%s"><div class="%s">%s<div class="iphone-content">%s</div></div></div></div>',
        esc_attr($mockup_class),
        esc_attr($frame_class),
        esc_attr($screen_class),
        $camera_element,
        $output
    );
}


/**
 * نمایش صفحه انتخاب برند
 * >> این تابع به طور کامل اصلاح شده است <<
 */
function it_render_brand_selection_page() {
    // >> تابع نمایش برندها برای خواندن از تنظیمات جدید اصلاح شد <<
    $managed_brands = get_option('it_managed_brands');

    // اگر هیچ برندی در تنظیمات یافت نشد، از لیست پیش‌فرض استفاده کن
    if (empty($managed_brands)) {
        $default_brands = function_exists('it_get_predefined_brands') ? it_get_predefined_brands() : [];
        $managed_brands = [];
        foreach (array_keys($default_brands) as $brand_name) {
            $managed_brands[] = ['name' => $brand_name, 'enabled' => true];
        }
    }

    // فیلتر کردن برای نمایش فقط برندهای فعال
    $active_brand_names = [];
    foreach ($managed_brands as $brand) {
        if ($brand['enabled']) {
            $active_brand_names[] = $brand['name'];
        }
    }
    
    if (empty($active_brand_names)) {
        echo '<p>در حال حاضر هیچ برندی برای نمایش فعال نشده است.</p>';
        return;
    }

    // دریافت دسته‌بندی‌ها از ووکامرس بر اساس نام‌های فعال و مرتب‌شده
    $brands = get_terms([
        'taxonomy'   => 'product_cat',
        'name'       => $active_brand_names,
        'hide_empty' => false,
        'orderby'    => 'include',
        'parent'     => 0
    ]);
    if (isset($_GET['it_error'])) {
    $error_message = sanitize_text_field(urldecode($_GET['it_error']));

    echo '<div class="it-error-box">';
    echo '<strong>خطا:</strong> ' . esc_html($error_message);
    echo '</div>';
}
    ?>
    <div id="it-brand-selection-page">
        <!-- <h2 class="it-page-title">انتخاب برند</h2> -->
        <div class="it-brand-grid">
            <?php
            if (!empty($brands) && !is_wp_error($brands)) {
                // To maintain the order from settings, we sort the results from get_terms
                $ordered_brands = [];
                foreach ($active_brand_names as $name) {
                    foreach ($brands as $brand_obj) {
                        if ($brand_obj->name === $name) {
                            $ordered_brands[] = $brand_obj;
                            break;
                        }
                    }
                }

                foreach ($ordered_brands as $brand) {
                    // نام فایل تصویر را از متادیتای جدید می‌خوانیم
                    $image_filename = get_term_meta($brand->term_id, 'brand_image_file', true);
                    $image_url = '';

                    if ($image_filename) {
                        // آدرس کامل تصویر را با استفاده از آدرس افزونه و نام فایل می‌سازیم
                        $image_url = IT_PLUGIN_URL . 'assets/pics/brand/' . $image_filename;
                    } else {
                        // اگر به هر دلیلی نام فایل وجود نداشت، از تصویر جایگزین استفاده کن
                        $image_url = wc_placeholder_img_src();
                    }
                    ?>
                    <div class="it-brand-item" data-brandid="<?php echo esc_attr($brand->term_id); ?>">
                        <div class="it-brand-image">
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($brand->name); ?>">
                        </div>
                        <span class="it-brand-name"><?php echo esc_html($brand->name); ?></span>
                    </div>
                    <?php
                }
            } else {
                echo '<p>هیچ برندی برای نمایش یافت نشد. (برای درون‌ریزی، گزینه برندینگ را در تنظیمات افزونه فعال کنید)</p>';
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * نمایش فرم اصلی سفارش (ممکن است با ایجکس بارگذاری شود)
 */
function it_render_main_order_form($categories = null) {
    if ($categories === null) {
        // اگر دسته‌بندی‌ها از طریق ایجکس نیامده باشند، از تنظیمات بخوان
        $selected_cat_ids = get_option('it_selected_categories', array());
        if (empty($selected_cat_ids)) {
            echo '<p>هیچ دسته‌بندی برای نمایش تنظیم نشده است.</p>';
            return;
        }
        $categories = get_terms(['taxonomy' => 'product_cat', 'include' => $selected_cat_ids, 'hide_empty' => false]);
    }
    $user_wallet_balance = 0;
    if (is_user_logged_in() && function_exists('woo_wallet')) {
    $user_wallet_balance = woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), 'raw');
    }
    
    ?>
    <form id="it-multi-step-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-wallet-balance="<?php echo esc_attr($user_wallet_balance); ?>">
        <input type="hidden" name="action" value="handle_instagram_form">
        <?php wp_nonce_field('handle_instagram_form_nonce', '_wpnonce'); ?>
        
        <div id="it-step-1" class="it-step">
            <h2 class="it-page-title">مرحله ۱: انتخاب سرویس</h2>
            <select name="category_id" id="it-category-select" required>
                <option value="">یک سرویس را انتخاب کنید...</option>
                <?php if (!empty($categories) && !is_wp_error($categories)) : ?>
                    <?php foreach ($categories as $category) : ?>
                        <option value="<?php echo esc_attr($category->term_id); ?>"><?php echo esc_html($category->name); ?></option>
                    <?php endforeach; ?>
                <?php else: ?>
                       <option value="" disabled>سرویسی برای این برند یافت نشد.</option>
                <?php endif; ?>
            </select>
        </div>
        
        <div id="it-step-2" class="it-step" style="display:none;"><h2>مرحله ۲: انتخاب بسته</h2><select name="product_id" id="it-product-select" required><option value="">لطفاً صبر کنید...</option></select></div>
        <div id="it-step-3" class="it-step" style="display:none;">
            <h2>مرحله ۳: مشخصات</h2>
            <div id="it-product-description"></div>
            <label for="it-instagram-id-input">آیدی مورد نظر:</label>
            <input type="text" id="it-instagram-id-input" name="instagram_id" placeholder="مثال: username" required>
            <br>
        </div>
        <div id="it-step-4" class="it-step" style="display:none;">
            <label for="it-quantity-input">تعداد:</label>
            <p id="it-quantity-validation-msg" class="it-validation-error" style="display: none;"></p>
            <input type="number" id="it-quantity-input" name="quantity" min="1" placeholder="تعداد را وارد کنید" required>
            <p id="it-quantity-rules" style="font-size: 12px; color: #777; margin-top: 5px; display: none;"></p>
        </div>
        <div id="it-step-5" class="it-step" style="display:none;">
            <h2>پیش‌فاکتور و پرداخت</h2>
            <div id="it-invoice-details"></div>
            <div id="it-payment-section" class="woocommerce">
                <h3>روش پرداخت را انتخاب کنید:</h3>
                <?php
                if (class_exists('WooCommerce')) {
                    $all_available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                    $our_enabled_gateway_ids = get_option('it_enabled_payment_gateways', array());
                    $gateways_to_display = [];
                    if (!empty($all_available_gateways) && !empty($our_enabled_gateway_ids)) {
                        foreach ($all_available_gateways as $gateway) {
                            // ابتدا بررسی می‌کنیم که درگاه در تنظیمات افزونه فعال باشد
                            if (in_array($gateway->id, $our_enabled_gateway_ids)) {
                                
                                if ($gateway->id === 'wallet' && !is_user_logged_in()) {
                                    continue; // این درگاه را به لیست اضافه نکن و به سراغ بعدی برو
                                }

                                $gateways_to_display[$gateway->id] = $gateway;
                            }
                        }
                    }

                    if (!empty($gateways_to_display)) {
                        $default_gateway = get_option('woocommerce_default_gateway');
                        if (empty($default_gateway) || !array_key_exists($default_gateway, $gateways_to_display)) {
                            $default_gateway = key($gateways_to_display);
                        }
                        echo '<ul class="wc_payment_methods payment_methods methods">';
                        foreach ($gateways_to_display as $gateway) { ?>
                            <li class="wc_payment_method payment_method_<?php echo esc_attr($gateway->id); ?>">
                                <input id="payment_method_<?php echo esc_attr($gateway->id); ?>" type="radio" class="input-radio" name="payment_method" value="<?php echo esc_attr($gateway->id); ?>" />
                                <label for="payment_method_<?php echo esc_attr($gateway->id); ?>"><?php echo $gateway->get_title(); ?> <?php echo $gateway->get_icon(); ?></label>
                                <?php if ($gateway->has_fields() || $gateway->get_description()) {
                                    echo '<div class="payment_box payment_method_' . esc_attr($gateway->id) . '" style="display:none;">';
                                    $gateway->payment_fields();
                                    echo '</div>';
                                } ?>
                            </li>
                        <?php }
                        echo '</ul>';
                    } else {
                        echo '<p>متاسفانه در حال حاضر هیچ روش پرداختی در دسترس نیست.</p>';
                    }
                }
                ?>
            </div>
            <button type="submit" id="it-place-order-btn">ثبت سفارش و پرداخت</button>
        </div>
    </form>
    <?php
}

/**
 * تابع ایجکس برای دریافت سرویس‌های یک برند و نمایش فرم
 */
function it_ajax_get_brand_services() {
    check_ajax_referer('it_form_nonce', 'nonce');
    
    $brand_id = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0;
    if (!$brand_id) {
        wp_send_json_error(['message' => 'شناسه برند نامعتبر است.']);
    }

    // دریافت زیرمجموعه‌های برند (سرویس‌ها)
    $services = get_terms([
        'taxonomy'   => 'product_cat',
        'parent'     => $brand_id,
        'hide_empty' => false
    ]);

    ob_start();
    it_render_main_order_form($services);
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}

function it_render_auth_tabs() {
    ?>
    <div class="it-auth-container">
        <h2 class="auth-main-title">برای ادامه وارد شوید یا ثبت‌نام کنید</h2>
        <div class="it-auth-tabs">
            <button class="it-auth-tab active" data-tab="login">ورود</button>
            <button class="it-auth-tab" data-tab="register">ثبت نام</button>
            <button class="it-auth-tab" data-tab="forgot">فراموشی رمز عبور</button>
        </div>
        <div class="it-auth-content">
            <div id="it-auth-status-msg" class="status-msg"></div>

            <div id="login" class="it-auth-tab-content active">
                <form id="it-login-form" class="it-auth-form" method="post">
                    <input type="email" name="username" placeholder="ایمیل" required>
                    <input type="password" name="password" placeholder="رمز عبور" required>
                    <button type="submit" id="it-login-btn">ورود</button>
                </form>
            </div>
            <div id="register" class="it-auth-tab-content">
                <form id="it-register-form" class="it-auth-form" method="post">
                     <p class="form-description">ایمیل خود را وارد کنید. یک لینک برای تکمیل ثبت‌نام به شما ارسال خواهد شد.</p>
                    <input type="email" name="email" placeholder="ایمیل" required>
                    <button type="submit" id="it-register-btn">ثبت نام</button>
                </form>
            </div>
            <div id="forgot" class="it-auth-tab-content">
                <form id="it-forgot-password-form" class="it-auth-form" method="post">
                     <p class="form-description">ایمیل خود را وارد کنید تا لینک بازیابی رمز عبور برایتان ارسال شود.</p>
                    <input type="email" name="email" placeholder="ایمیل" required>
                    <button type="submit" id="it-forgot-btn">ارسال لینک بازیابی</button>
                </form>
            </div>
        </div>
    </div>
    <?php
}

function it_render_set_password_form(){
    ?>
    <div class="it-auth-container">
         <h2 class="auth-main-title">تنظیم رمز عبور دائمی</h2>
         <form id="it-set-password-form" class="it-auth-form" method="post">
             <p class="form-description">این اولین ورود شماست. لطفاً یک رمز عبور دائمی برای حساب خود انتخاب کنید.</p>
             <div id="it-password-status-msg" class="status-msg"></div>
             <input type="password" name="password_1" id="it-password-1" placeholder="رمز عبور جدید (حداقل ۸ کاراکتر)" required>
             <input type="password" name="password_2" id="it-password-2" placeholder="تکرار رمز عبور جدید" required>
             <button type="submit" id="it-set-password-btn">ذخیره رمز عبور</button>
         </form>
    </div>
    <?php
}

function it_render_reset_password_form(){
    $user = check_password_reset_key($_GET['it_reset_key'], $_GET['it_login']);
    if (is_wp_error($user)) {
        echo '<div class="status-msg error" style="display:block;">لینک بازیابی رمز عبور نامعتبر یا منقضی شده است. لطفاً دوباره تلاش کنید.</div>';
    } else {
        ?>
        <div class="it-auth-container">
             <h2 class="auth-main-title">بازیابی رمز عبور</h2>
             <form id="it-reset-password-form" class="it-auth-form" method="post">
                 <p class="form-description">یک رمز عبور جدید برای حساب کاربری <?php echo esc_html($user->user_login); ?> وارد کنید.</p>
                 <div id="it-reset-password-status-msg" class="status-msg"></div>
                 <input type="hidden" name="reset_key" value="<?php echo esc_attr($_GET['it_reset_key']); ?>">
                 <input type="hidden" name="user_login" value="<?php echo esc_attr($_GET['it_login']); ?>">
                 <input type="password" name="new_password_1" id="it-new-password-1" placeholder="رمز عبور جدید" required>
                 <input type="password" name="new_password_2" id="it-new-password-2" placeholder="تکرار رمز عبور جدید" required>
                 <button type="submit" id="it-reset-password-btn">تغییر رمز عبور</button>
             </form>
        </div>
        <?php
    }
}

function it_get_products_by_category() {
    check_ajax_referer('get_products_nonce', 'nonce');
    $cat_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    if ($cat_id === 0) { wp_send_json_error('شناسه دسته‌بندی نامعتبر است.'); }
    $category = get_term($cat_id, 'product_cat');
    if (is_wp_error($category) || !$category) { wp_send_json_error('دسته‌بندی انتخاب شده یافت نشد.'); }
    
    $products = wc_get_products(array('category' => array($category->slug),'status' => 'publish','limit' => -1));
    $product_data = array();
    foreach ($products as $product) {
        $min_qty = $product->get_meta('_it_min_quantity');
        $max_qty = $product->get_meta('_it_max_quantity');
        $product_data[] = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => $product->get_price(),
            'short_description' => esc_attr(nl2br(wp_kses_post($product->get_description()))),
            'min_qty' => !empty($min_qty) ? (int)$min_qty : 1,
            'max_qty' => !empty($max_qty) ? (int)$max_qty : '',
        );
    }
    wp_send_json_success($product_data);
}

function it_render_my_orders_list_shortcode() {
    if (isset($_GET['view-order'])) { return ''; }
    if (!is_user_logged_in()) {
        $form_page_id = get_option('it_form_page_id');
        $login_url = $form_page_id ? get_permalink($form_page_id) : wp_login_url(get_permalink());

        $theme = get_option('it_device_theme', 'iphone');
        $mockup_class = ($theme === 'samsung') ? 'samsung-mockup' : 'iphone-mockup';
        $frame_class = ($theme === 'samsung') ? 'samsung-frame' : 'iphone-frame';
        $screen_class = ($theme === 'samsung') ? 'samsung-screen' : 'iphone-screen';
        $camera_element = ($theme === 'samsung') ? '<div class="punch-hole-camera"></div>' : '<div class="dynamic-island"></div>';

        return '<div class="' . esc_attr($mockup_class) . '"><div class="' . esc_attr($frame_class) . '"><div class="' . esc_attr($screen_class) . '">' . $camera_element . '<div class="iphone-content"><div class="it-auth-container"><h2 class="auth-main-title">برای مشاهده سفارش‌های خود، لطفاً ابتدا <a href="'.esc_url($login_url).'">وارد شوید</a>.</h2></div></div></div></div></div>';
    }

    $orders_per_page = get_option('it_orders_per_page', 9);
    $customer_orders = wc_get_orders([
        'customer_id' => get_current_user_id(), 'limit' => $orders_per_page, 'paged' => 1,
        'meta_key' => '_billing_instagram_id', 'meta_compare' => 'EXISTS',
        'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects',
    ]);
    
    $all_orders_query = new WC_Order_Query([
        'customer_id' => get_current_user_id(), 'meta_key' => '_billing_instagram_id',
        'limit' => -1, 'return' => 'ids',
    ]);
    $total_orders = count($all_orders_query->get_orders());
    
    $view_order_button_text = get_option('it_view_order_button_text', 'مشاهده سفارش');

    ob_start();
    ?>
    <div class="it-orders-list-wrapper">
        <h2>سفارش‌های من</h2>
        <?php if (empty($customer_orders)) : ?>
            <p style="text-align: center; color: #fff; text-shadow: 1px 1px 2px rgba(0,0,0,0.2);">شما تاکنون هیچ سفارشی ثبت نکرده‌اید.</p>
        <?php else : ?>
            <div class="it-explore-grid">
                <?php echo it_get_order_items_html($customer_orders, $view_order_button_text); ?>
            </div>
            
            <?php if ($total_orders > $orders_per_page) : ?>
                <div class="it-load-more-container">
                    <button id="it-load-more-btn" data-page="1" data-per-page="<?php echo esc_attr($orders_per_page); ?>">نمایش بیشتر</button>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function it_ajax_load_more_orders_html() {
    check_ajax_referer('it_get_order_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(); }

    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = get_option('it_orders_per_page', 9);
    
    $customer_orders = wc_get_orders([
        'customer_id' => get_current_user_id(), 'limit' => $per_page, 'paged' => $page + 1,
        'meta_key' => '_billing_instagram_id', 'meta_compare' => 'EXISTS',
        'orderby' => 'date', 'order' => 'DESC',
    ]);

    if (empty($customer_orders)) {
        wp_send_json_success(['html' => '', 'has_more' => false]);
        return;
    }

    $view_order_button_text = get_option('it_view_order_button_text', 'مشاهده سفارش');
    $html = it_get_order_items_html($customer_orders, $view_order_button_text);
    
    $total_query = new WC_Order_Query(['customer_id' => get_current_user_id(), 'meta_key' => '_billing_instagram_id', 'return' => 'ids', 'limit' => -1]);
    $total_orders = count($total_query->get_orders());
    $has_more = $total_orders > (($page + 1) * $per_page);

    wp_send_json_success(['html' => $html, 'has_more' => $has_more]);
}

function it_get_order_items_html($orders, $button_text = 'مشاهده سفارش') {
    ob_start();
    foreach ($orders as $order) {
        $item = array_values($order->get_items())[0] ?? null;
        $product = $item ? $item->get_product() : null;
        $image_id = $product ? $product->get_image_id() : 0;
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src('thumbnail');
        $order_number = $order->get_order_number();
        $order_date = wc_format_datetime($order->get_date_created(), 'Y/m/d');
        ?>
        <div class="it-explore-item" data-order-id="<?php echo esc_attr($order->get_id()); ?>" data-order-key="<?php echo esc_attr($order->get_order_key()); ?>">
            <div class="item-content-wrapper">
                <div class="item-image-wrapper">
                    <img src="<?php echo esc_url($image_url); ?>" alt="تصویر محصول سفارش" loading="lazy">
                </div>
                <span class="item-order-number"><?php echo '#' . esc_html($order_number); ?></span>
                <span class="item-order-date"><?php echo esc_html($order_date); ?></span>
                <button class="it-view-order-btn"><?php echo esc_html($button_text); ?></button>
            </div>
        </div>
        <?php
    }
    return ob_get_clean();
}

function it_ajax_get_order_details_html() {
    check_ajax_referer('it_get_order_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'لطفا ابتدا وارد شوید.']); }

    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $order_key = isset($_POST['order_key']) ? wc_clean($_POST['order_key']) : '';
    if (!$order_id || empty($order_key)) { wp_send_json_error(['message' => 'اطلاعات سفارش نامعتبر است.']); }
    
    $order = wc_get_order($order_id);
    if (!$order || $order->get_customer_id() !== get_current_user_id() || !hash_equals($order->get_order_key(), $order_key)) {
        wp_send_json_error(['message' => 'شما اجازه دسترسی به این سفارش را ندارید.']);
    }

    $api_status_data = null;
    $api_order_id = $order->get_meta('_api_order_id');
    $api_provider = $order->get_meta('_api_provider'); // دریافت ارائه‌دهنده از سفارش

    if ($api_order_id && $api_provider) {
        // تعیین کلید و آدرس API بر اساس ارائه‌دهنده ذخیره شده در سفارش
        if ($api_provider === 'followeran') {
            $api_key = get_option('it_api_key_followeran');
            $api_url = 'https://panel.followeran.com/api/v2';
        } else {
            $api_key = get_option('it_api_key_panelbaz');
            $api_url = 'https://panelbaz.ir/panelbaz/api/v1';
        }

        if ($api_key) {
            $response = wp_remote_post($api_url, [
                'body' => [
                    'key'    => $api_key,
                    'action' => 'status',
                    // پارامترهای متفاوت برای هر API
                    ($api_provider === 'followeran' ? 'order' : 'orders') => $api_order_id,
                ]
            ]);

            if (!is_wp_error($response)) {
                $result = json_decode(wp_remote_retrieve_body($response), true);
                
                // بررسی پاسخ بر اساس ساختار هر API
                if ($api_provider === 'followeran' && isset($result['status'])) {
                    $api_status_data = [
                        'start_count' => $result['start_count'] ?? 'N/A',
                        'remains'     => $result['remains'] ?? 'N/A'
                    ];
                } elseif ($api_provider === 'panelbaz' && isset($result[$api_order_id]['status'])) {
                    $order_result = $result[$api_order_id];
                    $api_status_data = [
                        'start_count' => $order_result['start_count'] ?? 'N/A',
                        'remains'     => $order_result['remains'] ?? 'N/A'
                    ];
                }

                if ($api_status_data) {
                    $order->update_meta_data('_api_start_count', $api_status_data['start_count']);
                    $order->update_meta_data('_api_remains', $api_status_data['remains']);
                    $order->save();
                }
            }
        }
    }
    
    global $it_order_for_template;
    $it_order_for_template = $order;

    ob_start();
    include IT_PLUGIN_DIR . 'templates/view-instagram-order.php';
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html, 'api_status' => $api_status_data]);
}
?>