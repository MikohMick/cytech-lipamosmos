<?php
/**
 * Plugin Name: Simple Lipa Polepole Calculator
 * Description: Simple payment calculator for iPhones with variable product support
 * Version: 3.0
 * Author: Cytech Digitals
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_Calculator_Plugin {

    public function __construct() {
        add_filter('woocommerce_short_description', array($this, 'add_calculator'), 10, 1);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Admin settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));

        // Price display override
        add_filter('woocommerce_get_price_html', array($this, 'modify_price_display'), 10, 2);

        // Product save hook for automatic price conversion
        add_action('woocommerce_update_product', array($this, 'auto_convert_product_price'), 10, 1);
        add_action('woocommerce_new_product', array($this, 'auto_convert_product_price'), 10, 1);

        // AJAX handlers
        add_action('wp_ajax_lipa_polepole_convert_products', array($this, 'ajax_convert_products'));
        add_action('wp_ajax_lipa_polepole_revert_products', array($this, 'ajax_revert_products'));

        // Admin product fields
        add_action('woocommerce_product_options_pricing', array($this, 'add_custom_price_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_custom_price_field'));
        add_action('woocommerce_variation_options_pricing', array($this, 'add_variation_price_field'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_price_field'), 10, 2);

        // REST API support
        add_action('rest_api_init', array($this, 'register_rest_fields'));
    }

    // Add admin menu
    public function add_admin_menu() {
        add_menu_page(
            'Lipa Polepole Settings',
            'Lipa Polepole',
            'manage_options',
            'lipa-polepole-settings',
            array($this, 'settings_page'),
            'dashicons-calculator',
            56
        );
    }

    // Register settings
    public function register_settings() {
        register_setting('lipa_polepole_settings', 'lipa_polepole_categories');
        register_setting('lipa_polepole_settings', 'lipa_polepole_whatsapp');
        register_setting('lipa_polepole_settings', 'lipa_polepole_payment_plans');
    }

    // Get default payment plans
    private function get_default_payment_plans() {
        return array(
            array('weeks' => 2, 'interest' => 25, 'deposit' => 40),
            array('weeks' => 4, 'interest' => 30, 'deposit' => 40),
            array('weeks' => 8, 'interest' => 40, 'deposit' => 40),
            array('weeks' => 12, 'interest' => 50, 'deposit' => 40),
            array('weeks' => 16, 'interest' => 60, 'deposit' => 50),
            array('weeks' => 20, 'interest' => 70, 'deposit' => 50),
            array('weeks' => 24, 'interest' => 80, 'deposit' => 50),
        );
    }

    // Settings page HTML
    public function settings_page() {
        // Handle form submission
        if (isset($_POST['lipa_polepole_save_settings']) && check_admin_referer('lipa_polepole_settings_nonce')) {
            // Save categories
            $categories = isset($_POST['lipa_polepole_categories']) ? array_map('intval', $_POST['lipa_polepole_categories']) : array();
            update_option('lipa_polepole_categories', $categories);

            // Save WhatsApp number
            $whatsapp = isset($_POST['lipa_polepole_whatsapp']) ? sanitize_text_field($_POST['lipa_polepole_whatsapp']) : '';
            update_option('lipa_polepole_whatsapp', $whatsapp);

            // Save payment plans
            $payment_plans = array();
            if (isset($_POST['payment_plans']) && is_array($_POST['payment_plans'])) {
                foreach ($_POST['payment_plans'] as $plan) {
                    $payment_plans[] = array(
                        'weeks' => intval($plan['weeks']),
                        'interest' => intval($plan['interest']),
                        'deposit' => intval($plan['deposit']),
                    );
                }
            }
            update_option('lipa_polepole_payment_plans', $payment_plans);

            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }

        // Get current settings
        $selected_categories = get_option('lipa_polepole_categories', array());
        $whatsapp = get_option('lipa_polepole_whatsapp', '254726166061');
        $payment_plans = get_option('lipa_polepole_payment_plans', $this->get_default_payment_plans());

        // Get all product categories
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ));

        ?>
        <style>
        .lipa-polepole-settings-wrap {
            max-width: 1200px;
        }
        .lipa-settings-card {
            background: #fff;
            padding: 25px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .lipa-settings-card h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #2271b1;
            color: #2271b1;
        }
        .lipa-settings-card p.description {
            color: #666;
            margin-bottom: 20px;
        }
        .category-selection-wrapper {
            max-height: 400px;
            overflow-y: auto;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
        }
        .category-selection-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
        }
        .category-selection-header .button {
            margin-left: 10px;
        }
        .category-item {
            margin: 8px 0;
            padding: 8px;
            background: #fff;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .category-item:hover {
            background: #f0f7ff;
        }
        .category-item label {
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        .category-item input[type="checkbox"] {
            margin-right: 8px;
        }
        .category-child {
            margin-left: 30px;
            border-left: 2px solid #e0e0e0;
            padding-left: 15px;
        }
        .category-count {
            color: #666;
            font-size: 12px;
            margin-left: 5px;
        }
        </style>

        <div class="wrap lipa-polepole-settings-wrap">
            <h1>🧮 Lipa Polepole Calculator Settings</h1>
            <p style="font-size: 16px; color: #666; margin-bottom: 30px;">Configure your payment calculator settings, categories, and pricing options.</p>

            <form method="post" action="">
                <?php wp_nonce_field('lipa_polepole_settings_nonce'); ?>

                <!-- Category Selection -->
                <div class="lipa-settings-card">
                    <h2>📁 Product Categories</h2>
                    <p class="description">Select the categories where the Lipa Polepole calculator should appear. Only products in these categories will show the payment calculator.</p>

                    <div class="category-selection-header">
                        <strong>Select Categories:</strong>
                        <div>
                            <button type="button" class="button" id="selectAllCategories">Select All</button>
                            <button type="button" class="button" id="deselectAllCategories">Deselect All</button>
                        </div>
                    </div>

                    <div class="category-selection-wrapper">
                        <?php
                        if (!empty($categories)) {
                            $this->render_category_checkboxes($categories, $selected_categories);
                        } else {
                            echo '<p>No categories found.</p>';
                        }
                        ?>
                    </div>
                </div>

                <!-- WhatsApp Number -->
                <div class="lipa-settings-card">
                    <h2>💬 WhatsApp Number</h2>
                    <p class="description">Enter your WhatsApp business number (with country code). Customers will use this to complete their orders.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="lipa_polepole_whatsapp">WhatsApp Number</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="lipa_polepole_whatsapp"
                                       name="lipa_polepole_whatsapp"
                                       value="<?php echo esc_attr($whatsapp); ?>"
                                       class="regular-text"
                                       placeholder="254726166061"
                                       style="font-size: 16px;">
                                <p class="description">Format: Country code + number (e.g., 254726166061 for Kenya)</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Payment Plans -->
                <div class="lipa-settings-card">
                    <h2>📊 Payment Plans</h2>
                    <p class="description">Configure flexible payment plans for your customers. Each plan defines the duration, interest rate, and required deposit percentage.</p>
                    <table class="widefat" style="margin-bottom: 15px; border: 1px solid #ddd;">
                        <thead style="background: #f5f5f5;">
                            <tr>
                                <th style="padding: 12px;">Weeks</th>
                                <th style="padding: 12px;">Interest (%)</th>
                                <th style="padding: 12px;">Deposit (%)</th>
                                <th style="padding: 12px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="payment-plans-tbody">
                            <?php foreach ($payment_plans as $index => $plan): ?>
                            <tr>
                                <td>
                                    <input type="number"
                                           name="payment_plans[<?php echo $index; ?>][weeks]"
                                           value="<?php echo esc_attr($plan['weeks']); ?>"
                                           min="1"
                                           style="width: 100px;">
                                </td>
                                <td>
                                    <input type="number"
                                           name="payment_plans[<?php echo $index; ?>][interest]"
                                           value="<?php echo esc_attr($plan['interest']); ?>"
                                           min="0"
                                           max="100"
                                           style="width: 100px;">
                                </td>
                                <td>
                                    <input type="number"
                                           name="payment_plans[<?php echo $index; ?>][deposit]"
                                           value="<?php echo esc_attr($plan['deposit']); ?>"
                                           min="0"
                                           max="100"
                                           style="width: 100px;">
                                </td>
                                <td>
                                    <button type="button" class="button remove-plan" onclick="removePaymentPlan(this)">Remove</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="button" class="button" onclick="addPaymentPlan()" style="margin-top: 10px;">➕ Add Payment Plan</button>
                </div>

                <p class="submit" style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 8px; margin-top: 30px;">
                    <input type="submit"
                           name="lipa_polepole_save_settings"
                           class="button button-primary button-hero"
                           value="💾 Save All Settings"
                           style="font-size: 18px; padding: 10px 40px;">
                </p>
            </form>

            <!-- Bulk Conversion Section -->
            <div class="lipa-settings-card" style="border-left: 4px solid #ff9800;">
                <h2>🔄 Bulk Price Conversion</h2>
                <p class="description">Convert existing products in selected categories to show deposit prices (40% of full price). This will update all products at once.</p>
                <button type="button" id="convertProductsBtn" class="button button-secondary">
                    Convert Products in Selected Categories
                </button>
                <div id="convertProgress" style="display: none; margin-top: 15px;">
                    <div style="background: #f0f0f0; height: 20px; border-radius: 10px; overflow: hidden;">
                        <div id="convertProgressBar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <p id="convertStatus" style="margin-top: 10px;">Processing...</p>
                </div>
            </div>

            <!-- Revert Section -->
            <div class="lipa-settings-card" style="border-left: 4px solid #f44336;">
                <h2>↩️ Revert Prices</h2>
                <p class="description">Restore original full prices for products in selected categories. This will undo the deposit pricing conversion.</p>

                <div class="category-selection-wrapper" style="max-height: 300px;">
                    <p style="margin-bottom: 15px;"><strong>Select categories to revert:</strong></p>
                    <?php
                    if (!empty($categories)) {
                        foreach ($categories as $category) {
                            if ($category->parent == 0) {
                                echo '<div class="category-item">';
                                echo '<label>';
                                echo '<input type="checkbox" class="revert-category" value="' . esc_attr($category->term_id) . '"> ';
                                echo '<strong>' . esc_html($category->name) . '</strong> <span class="category-count">(' . $category->count . ' products)</span>';
                                echo '</label>';
                                echo '</div>';

                                // Render child categories
                                foreach ($categories as $child_cat) {
                                    if ($child_cat->parent == $category->term_id) {
                                        echo '<div class="category-item category-child">';
                                        echo '<label>';
                                        echo '<input type="checkbox" class="revert-category" value="' . esc_attr($child_cat->term_id) . '"> ';
                                        echo esc_html($child_cat->name) . ' <span class="category-count">(' . $child_cat->count . ' products)</span>';
                                        echo '</label>';
                                        echo '</div>';
                                    }
                                }
                            }
                        }
                    }
                    ?>
                </div>
                <button type="button" id="revertProductsBtn" class="button button-secondary">
                    Revert Selected Categories
                </button>
                <div id="revertProgress" style="display: none; margin-top: 15px;">
                    <div style="background: #f0f0f0; height: 20px; border-radius: 10px; overflow: hidden;">
                        <div id="revertProgressBar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <p id="revertStatus" style="margin-top: 10px;">Processing...</p>
                </div>
            </div>
        </div>

        <script>
        var planIndex = <?php echo count($payment_plans); ?>;

        // Select All / Deselect All functionality
        document.getElementById('selectAllCategories').addEventListener('click', function() {
            document.querySelectorAll('input[name="lipa_polepole_categories[]"]').forEach(function(checkbox) {
                checkbox.checked = true;
            });
        });

        document.getElementById('deselectAllCategories').addEventListener('click', function() {
            document.querySelectorAll('input[name="lipa_polepole_categories[]"]').forEach(function(checkbox) {
                checkbox.checked = false;
            });
        });

        function addPaymentPlan() {
            var tbody = document.getElementById('payment-plans-tbody');
            var row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="number" name="payment_plans[${planIndex}][weeks]" value="4" min="1" style="width: 100px;"></td>
                <td><input type="number" name="payment_plans[${planIndex}][interest]" value="30" min="0" max="100" style="width: 100px;"></td>
                <td><input type="number" name="payment_plans[${planIndex}][deposit]" value="40" min="0" max="100" style="width: 100px;"></td>
                <td><button type="button" class="button remove-plan" onclick="removePaymentPlan(this)">Remove</button></td>
            `;
            tbody.appendChild(row);
            planIndex++;
        }

        function removePaymentPlan(button) {
            if (confirm('Are you sure you want to remove this payment plan?')) {
                button.closest('tr').remove();
            }
        }

        // Bulk Convert Products
        document.getElementById('convertProductsBtn').addEventListener('click', function() {
            if (!confirm('This will convert all products in selected categories. Continue?')) {
                return;
            }

            var btn = this;
            btn.disabled = true;
            document.getElementById('convertProgress').style.display = 'block';

            convertBatch(0);
        });

        function convertBatch(offset) {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'lipa_polepole_convert_products',
                    nonce: '<?php echo wp_create_nonce('lipa_polepole_convert'); ?>',
                    offset: offset
                },
                success: function(response) {
                    if (response.success) {
                        var percent = (response.data.processed / response.data.total) * 100;
                        document.getElementById('convertProgressBar').style.width = percent + '%';
                        document.getElementById('convertStatus').textContent =
                            'Processed ' + response.data.processed + ' of ' + response.data.total + ' products';

                        if (!response.data.done) {
                            convertBatch(response.data.processed);
                        } else {
                            document.getElementById('convertStatus').textContent = 'Conversion complete! ' + response.data.total + ' products processed.';
                            document.getElementById('convertProductsBtn').disabled = false;
                            setTimeout(function() {
                                document.getElementById('convertProgress').style.display = 'none';
                                document.getElementById('convertProgressBar').style.width = '0%';
                            }, 3000);
                        }
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        document.getElementById('convertProductsBtn').disabled = false;
                    }
                },
                error: function() {
                    alert('AJAX error occurred');
                    document.getElementById('convertProductsBtn').disabled = false;
                }
            });
        }

        // Revert Products
        document.getElementById('revertProductsBtn').addEventListener('click', function() {
            var selectedCategories = [];
            document.querySelectorAll('.revert-category:checked').forEach(function(checkbox) {
                selectedCategories.push(checkbox.value);
            });

            if (selectedCategories.length === 0) {
                alert('Please select at least one category to revert');
                return;
            }

            if (!confirm('This will restore original prices for products in selected categories. Continue?')) {
                return;
            }

            var btn = this;
            btn.disabled = true;
            document.getElementById('revertProgress').style.display = 'block';

            revertBatch(0, selectedCategories);
        });

        function revertBatch(offset, categories) {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'lipa_polepole_revert_products',
                    nonce: '<?php echo wp_create_nonce('lipa_polepole_revert'); ?>',
                    offset: offset,
                    categories: categories
                },
                success: function(response) {
                    if (response.success) {
                        var percent = (response.data.processed / response.data.total) * 100;
                        document.getElementById('revertProgressBar').style.width = percent + '%';
                        document.getElementById('revertStatus').textContent =
                            'Processed ' + response.data.processed + ' of ' + response.data.total + ' products';

                        if (!response.data.done) {
                            revertBatch(response.data.processed, categories);
                        } else {
                            document.getElementById('revertStatus').textContent = 'Revert complete! ' + response.data.total + ' products restored.';
                            document.getElementById('revertProductsBtn').disabled = false;
                            setTimeout(function() {
                                document.getElementById('revertProgress').style.display = 'none';
                                document.getElementById('revertProgressBar').style.width = '0%';
                            }, 3000);
                        }
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        document.getElementById('revertProductsBtn').disabled = false;
                    }
                },
                error: function() {
                    alert('AJAX error occurred');
                    document.getElementById('revertProductsBtn').disabled = false;
                }
            });
        }
        </script>
        <?php
    }

    // Render category checkboxes hierarchically
    private function render_category_checkboxes($categories, $selected_categories, $parent = 0, $level = 0) {
        foreach ($categories as $category) {
            if ($category->parent == $parent) {
                $checked = in_array($category->term_id, $selected_categories) ? 'checked' : '';
                $child_class = $level > 0 ? ' category-child' : '';

                echo '<div class="category-item' . $child_class . '">';
                echo '<label>';
                echo '<input type="checkbox" name="lipa_polepole_categories[]" value="' . esc_attr($category->term_id) . '" ' . $checked . '> ';
                if ($level == 0) {
                    echo '<strong>' . esc_html($category->name) . '</strong>';
                } else {
                    echo esc_html($category->name);
                }
                echo ' <span class="category-count">(' . $category->count . ' products)</span>';
                echo '</label>';
                echo '</div>';

                // Render child categories
                $this->render_category_checkboxes($categories, $selected_categories, $category->term_id, $level + 1);
            }
        }
    }

    // Check if product should show calculator
    private function should_show_calculator($product_id) {
        $selected_categories = get_option('lipa_polepole_categories', array());

        // If no categories selected, don't show calculator
        if (empty($selected_categories)) {
            return false;
        }

        // Check if product is in any of the selected categories
        foreach ($selected_categories as $cat_id) {
            if (has_term($cat_id, 'product_cat', $product_id)) {
                return true;
            }
        }

        return false;
    }

    // Get full price (from meta or current price)
    private function get_full_price($product_id) {
        $full_price = get_post_meta($product_id, '_lipa_polepole_full_price', true);

        if (!$full_price || $full_price <= 0) {
            // Fallback to current price
            $product = wc_get_product($product_id);
            if ($product) {
                $full_price = $product->get_price();
            }
        }

        return floatval($full_price);
    }

    // Auto-convert product price on save/update
    public function auto_convert_product_price($product_id) {
        // Check if product is in selected categories
        if (!$this->should_show_calculator($product_id)) {
            return;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        // Handle variable products
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();

            foreach ($variations as $variation_data) {
                $variation_id = $variation_data['variation_id'];
                $variation = wc_get_product($variation_id);

                if (!$variation) {
                    continue;
                }

                $current_price = floatval($variation->get_regular_price());

                if ($current_price <= 0) {
                    continue;
                }

                $existing_full_price = get_post_meta($variation_id, '_lipa_polepole_full_price', true);

                if (!$existing_full_price) {
                    // First time conversion
                    update_post_meta($variation_id, '_lipa_polepole_full_price', $current_price);

                    $deposit_price = $current_price * 0.40;
                    $variation->set_regular_price($deposit_price);
                    $variation->set_price($deposit_price);
                    $variation->save();
                } else {
                    // Check if admin changed the price
                    $deposit_price = floatval($existing_full_price) * 0.40;

                    if (abs($current_price - $deposit_price) > 0.01) {
                        update_post_meta($variation_id, '_lipa_polepole_full_price', $current_price);

                        $new_deposit = $current_price * 0.40;
                        $variation->set_regular_price($new_deposit);
                        $variation->set_price($new_deposit);
                        $variation->save();
                    }
                }
            }
        } else {
            // Handle simple products
            $current_price = floatval($product->get_regular_price());

            // Skip if no price
            if ($current_price <= 0) {
                return;
            }

            // Check if already converted (has full price meta)
            $existing_full_price = get_post_meta($product_id, '_lipa_polepole_full_price', true);

            if (!$existing_full_price) {
                // First time conversion - save current price as full price
                update_post_meta($product_id, '_lipa_polepole_full_price', $current_price);

                // Set product price to 40% deposit
                $deposit_price = $current_price * 0.40;
                $product->set_regular_price($deposit_price);
                $product->set_price($deposit_price);
                $product->save();
            } else {
                // Already converted - check if admin changed the price
                $deposit_price = floatval($existing_full_price) * 0.40;

                // If current price differs significantly from expected deposit, admin changed it
                if (abs($current_price - $deposit_price) > 0.01) {
                    // Assume admin changed full price, update meta and recalculate
                    update_post_meta($product_id, '_lipa_polepole_full_price', $current_price);

                    $new_deposit = $current_price * 0.40;
                    $product->set_regular_price($new_deposit);
                    $product->set_price($new_deposit);
                    $product->save();
                }
            }
        }
    }

    // AJAX: Bulk convert products
    public function ajax_convert_products() {
        check_ajax_referer('lipa_polepole_convert', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = 10;

        $selected_categories = get_option('lipa_polepole_categories', array());

        if (empty($selected_categories)) {
            wp_send_json_error('No categories selected');
        }

        // Get products in selected categories
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $selected_categories,
                    'operator' => 'IN',
                ),
            ),
        );

        $products = get_posts($args);

        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) {
                continue;
            }

            // Handle variable products
            if ($product->is_type('variable')) {
                $variations = $product->get_available_variations();

                foreach ($variations as $variation_data) {
                    $variation_id = $variation_data['variation_id'];
                    $variation = wc_get_product($variation_id);

                    if (!$variation) {
                        continue;
                    }

                    $current_price = floatval($variation->get_regular_price());

                    if ($current_price <= 0) {
                        continue;
                    }

                    $existing_full_price = get_post_meta($variation_id, '_lipa_polepole_full_price', true);

                    if (!$existing_full_price) {
                        update_post_meta($variation_id, '_lipa_polepole_full_price', $current_price);

                        $deposit_price = $current_price * 0.40;
                        $variation->set_regular_price($deposit_price);
                        $variation->set_price($deposit_price);
                        $variation->save();
                    }
                }
            } else {
                // Handle simple products
                $current_price = floatval($product->get_regular_price());

                if ($current_price <= 0) {
                    continue;
                }

                $existing_full_price = get_post_meta($post->ID, '_lipa_polepole_full_price', true);

                if (!$existing_full_price) {
                    update_post_meta($post->ID, '_lipa_polepole_full_price', $current_price);

                    $deposit_price = $current_price * 0.40;
                    $product->set_regular_price($deposit_price);
                    $product->set_price($deposit_price);
                    $product->save();
                }
            }
        }

        // Get total count
        $total_args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $selected_categories,
                    'operator' => 'IN',
                ),
            ),
        );
        $total_products = count(get_posts($total_args));

        $processed = $offset + count($products);
        $done = $processed >= $total_products;

        wp_send_json_success(array(
            'processed' => $processed,
            'total' => $total_products,
            'done' => $done,
        ));
    }

    // AJAX: Revert products
    public function ajax_revert_products() {
        check_ajax_referer('lipa_polepole_revert', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = 10;
        $categories_to_revert = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : array();

        if (empty($categories_to_revert)) {
            wp_send_json_error('No categories selected');
        }

        // Get products in selected categories
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $categories_to_revert,
                    'operator' => 'IN',
                ),
            ),
        );

        $products = get_posts($args);

        foreach ($products as $post) {
            $full_price = get_post_meta($post->ID, '_lipa_polepole_full_price', true);

            if ($full_price && $full_price > 0) {
                $product = wc_get_product($post->ID);
                if ($product) {
                    $product->set_regular_price($full_price);
                    $product->set_price($full_price);
                    $product->save();

                    // Delete the meta
                    delete_post_meta($post->ID, '_lipa_polepole_full_price');
                }
            }
        }

        // Get total count
        $total_args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $categories_to_revert,
                    'operator' => 'IN',
                ),
            ),
        );
        $total_products = count(get_posts($total_args));

        $processed = $offset + count($products);
        $done = $processed >= $total_products;

        wp_send_json_success(array(
            'processed' => $processed,
            'total' => $total_products,
            'done' => $done,
        ));
    }

    // Modify price display to show deposit
    public function modify_price_display($price_html, $product) {
        // Only modify if calculator should show for this product
        if (!$this->should_show_calculator($product->get_id())) {
            return $price_html;
        }

        // Handle variable products differently
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();

            if (empty($variations)) {
                return $price_html;
            }

            // Get min and max deposit prices from variations
            $min_deposit = null;
            $max_deposit = null;
            $min_full_price = null;
            $max_full_price = null;

            foreach ($variations as $variation_data) {
                $variation_id = $variation_data['variation_id'];
                $variation_deposit = $variation_data['display_price']; // This is already the 40% deposit
                $variation_full_price = get_post_meta($variation_id, '_lipa_polepole_full_price', true);

                if (!$variation_full_price) {
                    $variation_full_price = $variation_deposit * 2.5;
                }

                if ($min_deposit === null || $variation_deposit < $min_deposit) {
                    $min_deposit = $variation_deposit;
                    $min_full_price = $variation_full_price;
                }

                if ($max_deposit === null || $variation_deposit > $max_deposit) {
                    $max_deposit = $variation_deposit;
                    $max_full_price = $variation_full_price;
                }
            }

            // Format prices
            if ($min_deposit == $max_deposit) {
                // Single price
                $formatted_deposit = wc_price($min_deposit);
                $formatted_full_price = wc_price($min_full_price);
            } else {
                // Price range
                $formatted_deposit = wc_price($min_deposit) . ' - ' . wc_price($max_deposit);
                $formatted_full_price = wc_price($min_full_price) . ' - ' . wc_price($max_full_price);
            }

            return '<div class="lipa-polepole-price-display">
                        <span class="deposit-price" style="font-size: 1.2em; font-weight: bold;">Deposit: ' . $formatted_deposit . '</span>
                        <br>
                        <span class="original-price" style="text-decoration: line-through; color: #999; font-size: 0.9em;">Full Price: ' . $formatted_full_price . '</span>
                    </div>';
        } else {
            // Handle simple products
            $full_price = $this->get_full_price($product->get_id());

            if ($full_price <= 0) {
                return $price_html;
            }

            // Get deposit (current product price)
            $deposit = floatval($product->get_price());

            // Format prices
            $formatted_deposit = wc_price($deposit);
            $formatted_full_price = wc_price($full_price);

            // Return new price HTML with deposit and struck-through full price
            return '<div class="lipa-polepole-price-display">
                        <span class="deposit-price" style="font-size: 1.2em; font-weight: bold;">Deposit: ' . $formatted_deposit . '</span>
                        <br>
                        <span class="original-price" style="text-decoration: line-through; color: #999; font-size: 0.9em;">Full Price: ' . $formatted_full_price . '</span>
                    </div>';
        }
    }

    public function enqueue_scripts() {
        if (!is_product()) {
            return;
        }

        global $post;
        $product = wc_get_product($post->ID);

        // Get settings
        $whatsapp = get_option('lipa_polepole_whatsapp', '254726166061');
        $payment_plans = get_option('lipa_polepole_payment_plans', $this->get_default_payment_plans());

        // Get full price for current product
        $full_price = 0;
        $variation_full_prices = array();

        if ($product && is_object($product) && $this->should_show_calculator($product->get_id())) {
            if ($product->is_type('variable')) {
                // For variable products, get full price for each variation
                $variations = $product->get_available_variations();
                foreach ($variations as $variation_data) {
                    $variation_id = $variation_data['variation_id'];
                    $variation_full_price = get_post_meta($variation_id, '_lipa_polepole_full_price', true);

                    if (!$variation_full_price) {
                        // Fallback to display price * 2.5 if meta doesn't exist
                        $variation_full_price = $variation_data['display_price'] * 2.5;
                    }

                    $variation_full_prices[$variation_id] = floatval($variation_full_price);
                }
            } else {
                // For simple products
                $full_price = $this->get_full_price($product->get_id());
            }
        }

        wp_enqueue_script('jquery');

        // Pass settings to JavaScript
        wp_localize_script('jquery', 'lipaPolepoleSettings', array(
            'whatsapp' => $whatsapp,
            'paymentPlans' => $payment_plans,
            'fullPrice' => $full_price,
            'variationFullPrices' => $variation_full_prices,
        ));

        // Inline script
        wp_add_inline_script('jquery', '
        jQuery(document).ready(function($) {
            var variationsData = {};
            var isVariableProduct = false;
            var currentSelectedPrice = 0;
            var currentVariationText = "";

            // Check if this is a variable product and load variations
            if ($(".variations_form").length) {
                isVariableProduct = true;

                // Get variations data from WooCommerce
                var variationsForm = $(".variations_form");

                if (variationsForm.data("product_variations")) {
                    var variations = variationsForm.data("product_variations");

                    // Store variations by variation_id
                    $.each(variations, function(index, variation) {
                        if (variation.is_purchasable && variation.is_in_stock) {
                            // Get full price for this variation
                            var fullPrice = variation.display_price * 2.5; // Default fallback
                            if (lipaPolepoleSettings.variationFullPrices && lipaPolepoleSettings.variationFullPrices[variation.variation_id]) {
                                fullPrice = parseFloat(lipaPolepoleSettings.variationFullPrices[variation.variation_id]);
                            }

                            variationsData[variation.variation_id] = {
                                price: variation.display_price,
                                fullPrice: fullPrice,
                                attributes: variation.attributes,
                                variation_id: variation.variation_id
                            };
                        }
                    });
                }
            }

            // Function to get current product price (full price for calculations)
            function getCurrentPrice() {
                // PRIORITY 1: For variable products, use the full price from selected variation
                if (isVariableProduct && currentSelectedPrice > 0) {
                    return currentSelectedPrice;
                }

                // PRIORITY 2: Use full price from settings (for simple products)
                if (lipaPolepoleSettings.fullPrice && lipaPolepoleSettings.fullPrice > 0) {
                    return parseFloat(lipaPolepoleSettings.fullPrice);
                }

                // PRIORITY 3: Try to get from variation price display on page
                var variationPrice = $(".summary .woocommerce-variation-price .woocommerce-Price-amount bdi, .entry-summary .woocommerce-variation-price .woocommerce-Price-amount bdi").first();
                if (variationPrice.length && variationPrice.text()) {
                    var price = parseFloat(variationPrice.text().replace(/[^0-9.-]+/g,""));
                    if (price > 0) return price * 2.5; // Convert deposit back to full price (deposit is 40%, so full = deposit * 2.5)
                }

                // PRIORITY 4: Get regular product price from summary area
                var priceElement = $(".summary .price .woocommerce-Price-amount bdi, .entry-summary .price .woocommerce-Price-amount bdi").first();
                if (priceElement.length) {
                    var price = parseFloat(priceElement.text().replace(/[^0-9.-]+/g,""));
                    if (price > 0) return price * 2.5; // Convert deposit back to full price
                }

                // PRIORITY 5: Fallback for sale prices
                var salePrice = $(".summary .price ins .amount bdi, .entry-summary .price ins .amount bdi").first();
                if (salePrice.length) {
                    var price = parseFloat(salePrice.text().replace(/[^0-9.-]+/g,""));
                    if (price > 0) return price * 2.5; // Convert deposit back to full price
                }

                return 0;
            }

            // Build variation selector HTML
            function buildVariationSelector() {
                if (!isVariableProduct || Object.keys(variationsData).length === 0) {
                    return "";
                }

                var html = \'<div class="modal-variation-section">\';
                html += \'<label for="modalVariationSelect">Select Product Options:</label>\';
                html += \'<select id="modalVariationSelect">\';
                html += \'<option value="">Choose product options</option>\';

                $.each(variationsData, function(variationId, variation) {
                    var optionText = "";
                    $.each(variation.attributes, function(attrKey, attrValue) {
                        var label = attrKey.replace("attribute_pa_", "").replace("attribute_", "");
                        label = label.replace(/-/g, " ").replace(/_/g, " ");
                        label = label.split(" ").map(function(word) {
                            return word.charAt(0).toUpperCase() + word.slice(1);
                        }).join(" ");
                        optionText += label + ": " + attrValue + " | ";
                    });
                    optionText = optionText.slice(0, -3);

                    // Add price to option text
                    var formattedPrice = Math.round(variation.price).toString().replace(/\\B(?=(\\d{3})+(?!\\d))/g, ",");
                    optionText += " - Ksh " + formattedPrice;

                    html += \'<option value="\' + variationId + \'">\' + optionText + \'</option>\';
                });

                html += \'</select></div>\';
                return html;
            }

            // Open modal when calculate button is clicked
            $("#simpleCalculateBtn").click(function() {
                // Insert variation selector if it\'s a variable product
                if (isVariableProduct && !$("#modalVariationSelect").length) {
                    var variationHTML = buildVariationSelector();
                    if (variationHTML) {
                        $(variationHTML).insertBefore("#modalPlanSection");
                    }
                }

                $("#paymentModal").fadeIn(300);
                $("body").addClass("modal-open");
            });

            function simpleCalculate() {
                // For variable products, check if variation is selected first
                if (isVariableProduct && $("#modalVariationSelect").length) {
                    var selectedVariation = $("#modalVariationSelect").val();
                    if (!selectedVariation) {
                        $("#resultsSection").hide();
                        $("#whatsappBtn").hide();
                        return;
                    }
                }

                // Check if a payment plan is selected
                var planValue = $("#simplePlan").val();
                if (!planValue) {
                    $("#resultsSection").hide();
                    $("#whatsappBtn").hide();
                    return;
                }

                // Get current price
                var price = getCurrentPrice();

                if (!price || price <= 0) {
                    alert("Unable to get product price. Please select all product options and try again.");
                    return;
                }

                var parts = planValue.split(",");
                var weeks = parseInt(parts[0]);
                var interestRate = parseInt(parts[1]);
                var depositPercent = parseInt(parts[2]);

                var deposit = price * (depositPercent / 100);
                var balance = price - deposit;
                var interestAmount = balance * (interestRate / 100);
                var totalToPay = balance + interestAmount;
                var weeklyPayment = totalToPay / weeks;

                // Get product name for WhatsApp message
                var productName = $(".product_title, .entry-title, h1.product_title").first().text() || "iPhone";

                // Add variation details if any
                if (currentVariationText) {
                    productName += " (" + currentVariationText + ")";
                }

                // Add commas to numbers
                function addCommas(num) {
                    return Math.round(num).toString().replace(/\\B(?=(\\d{3})+(?!\\d))/g, ",");
                }

                // Update modal content
                $("#resultCash").text("Cash Price: Ksh " + addCommas(price));
                $("#resultDeposit").text("Deposit (" + depositPercent + "%): Ksh " + addCommas(deposit));
                $("#resultBalance").text("Balance: Ksh " + addCommas(balance));
                $("#resultInterest").text("Interest (" + interestRate + "%): Ksh " + addCommas(interestAmount));
                $("#resultWeekly").text("Weekly Payment: Ksh " + addCommas(weeklyPayment));
                $("#resultWeeks").text("Payment Period: " + weeks + " weeks");

                // Prepare WhatsApp message
                var whatsappMessage = "I want to order Lipa Pole Pole for " + productName +
                    "%0A%0APayment Plan: " + weeks + " weeks - " + interestRate + "% interest - " + depositPercent + "% deposit" +
                    "%0ACash Price: Ksh " + addCommas(price) +
                    "%0ADeposit Required: Ksh " + addCommas(deposit) +
                    "%0AWeekly Payment: Ksh " + addCommas(weeklyPayment) +
                    "%0A%0APlease confirm availability and next steps.";

                var whatsappNumber = lipaPolepoleSettings.whatsapp || "254726166061";
                var whatsappUrl = "https://wa.me/" + whatsappNumber + "?text=" + whatsappMessage;
                $("#whatsappBtn").attr("href", whatsappUrl);

                // Show results and WhatsApp button
                $("#resultsSection").show();
                $("#whatsappBtn").show();
            }

            // Handle variation selection change in modal
            $(document).on("change", "#modalVariationSelect", function() {
                var selectedVariationId = $(this).val();

                if (selectedVariationId && variationsData[selectedVariationId]) {
                    var variation = variationsData[selectedVariationId];

                    // Store the selected variation FULL price (for calculations)
                    currentSelectedPrice = parseFloat(variation.fullPrice || variation.price * 2.5);

                    // Build variation text for WhatsApp
                    var attrs = variation.attributes;
                    var variationText = "";
                    $.each(attrs, function(key, value) {
                        var label = key.replace("attribute_pa_", "").replace("attribute_", "");
                        label = label.replace(/-/g, " ").replace(/_/g, " ");
                        label = label.split(" ").map(function(word) {
                            return word.charAt(0).toUpperCase() + word.slice(1);
                        }).join(" ");
                        variationText += label + ": " + value + ", ";
                    });
                    currentVariationText = variationText ? variationText.slice(0, -2) : "";

                    // Automatically trigger calculation
                    simpleCalculate();
                } else {
                    // Reset if no variation selected
                    currentSelectedPrice = 0;
                    currentVariationText = "";
                    $("#resultsSection").hide();
                    $("#whatsappBtn").hide();
                }
            });

            // Auto-calculate when plan changes
            $("#simplePlan").change(function() {
                simpleCalculate();
            });

            // Close modal with animation
            $("#closeModal").click(function() {
                $("#paymentModal").fadeOut(300);
                $("body").removeClass("modal-open");
            });

            // Close modal when clicking outside
            $("#paymentModal").click(function(e) {
                if (e.target === this) {
                    $("#paymentModal").fadeOut(300);
                    $("body").removeClass("modal-open");
                }
            });

            // Escape key to close modal
            $(document).keydown(function(e) {
                if (e.keyCode === 27 && $("#paymentModal").is(":visible")) {
                    $("#paymentModal").fadeOut(300);
                    $("body").removeClass("modal-open");
                }
            });
        });
        ');

        // Inline CSS
        wp_add_inline_style('wp-block-library', '
        .simple-calculator {
            max-width: 400px;
            margin: 20px 0;
            padding: 25px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fff;
            position: relative;
            z-index: 1;
        }

        .simple-calculator button {
            width: 100%;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box;
            background: #007cba;
            color: white;
            cursor: pointer;
            border: none;
            transition: background 0.3s ease;
            font-weight: bold;
        }

        .simple-calculator button:hover {
            background: #005a87;
        }

        .cytech-requirements {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .cytech-requirements h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #000;
            font-size: 16px;
            font-weight: bold;
        }

        .cytech-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .cytech-requirements li {
            margin-bottom: 8px;
            font-size: 14px;
            color: #000;
            font-weight: bold;
        }

        /* Enhanced Modal Styles */
        .payment-modal {
            display: none;
            position: fixed;
            z-index: 999999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(3px);
        }

        .modal-content {
            background-color: #fff;
            margin: 2% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 550px;
            max-height: 90vh;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
            overflow: hidden;
        }

        .modal-scroll {
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .close-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 30px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
            transition: color 0.3s ease;
            z-index: 10;
        }

        .close-btn:hover {
            color: #007cba;
        }

        .modal-plan-section {
            margin-bottom: 25px;
            margin-top: 20px;
        }

        .modal-plan-section label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }

        .modal-plan-section select {
            width: 100%;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            background: #fff;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,<svg xmlns=\\"http://www.w3.org/2000/svg\\" viewBox=\\"0 0 4 5\\"><path fill=\\"%23666\\" d=\\"m0 1 2 2 2-2z\\"/></svg>");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
            padding-right: 40px;
        }

        .modal-plan-section select:focus {
            outline: none;
            border-color: #007cba;
            box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
        }

        .modal-variation-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .modal-variation-section label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }

        .modal-variation-section select {
            width: 100%;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            background: #fff;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,<svg xmlns=\\"http://www.w3.org/2000/svg\\" viewBox=\\"0 0 4 5\\"><path fill=\\"%23666\\" d=\\"m0 1 2 2 2-2z\\"/></svg>");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
            padding-right: 40px;
        }

        .modal-variation-section select:focus {
            outline: none;
            border-color: #007cba;
            box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
        }

        .modal-variation-section select:hover {
            border-color: #007cba;
        }

        .modal-results {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .modal-results p {
            margin: 12px 0;
            font-weight: bold;
            font-size: 16px;
            color: #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-results p:first-child {
            color: #007cba;
            font-size: 18px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .modal-results p:nth-child(5) {
            background: #25D366;
            color: white;
            padding: 12px;
            border-radius: 6px;
            font-size: 18px;
        }

        .whatsapp-btn {
            display: none;
            width: 100%;
            background: #25D366;
            color: white;
            text-decoration: none;
            padding: 15px 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            transition: background 0.3s ease;
            box-sizing: border-box;
        }

        .whatsapp-btn:hover {
            background: #1DA851;
            color: white;
            text-decoration: none;
        }

        body.modal-open {
            overflow: hidden;
        }

        /* Custom scrollbar for modal */
        .modal-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .modal-scroll::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .modal-scroll::-webkit-scrollbar-thumb {
            background: #007cba;
            border-radius: 3px;
        }

        .modal-scroll::-webkit-scrollbar-thumb:hover {
            background: #005a87;
        }

        @media (max-width: 600px) {
            .modal-content {
                margin: 5% auto;
                width: 95%;
                max-height: 85vh;
            }

            .modal-scroll {
                padding: 20px;
                max-height: 85vh;
            }

            .modal-results p {
                font-size: 14px;
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .whatsapp-btn {
                font-size: 14px;
                padding: 12px 15px;
            }
        }
        ');
    }

    public function add_calculator($content) {
        if (!is_product()) {
            return $content;
        }

        global $post;
        $product = wc_get_product($post->ID);

        // Check if calculator should show for this product
        if (!$product || !is_object($product) || !$this->should_show_calculator($product->get_id())) {
            return $content;
        }

        // Get payment plans from settings
        $payment_plans = get_option('lipa_polepole_payment_plans', $this->get_default_payment_plans());

        $calculator_html = '
        <div class="simple-calculator">
            <h3 style="margin-top: 0; margin-bottom: 20px;">Lipa Polepole Calculator</h3>

            <button id="simpleCalculateBtn">Calculate Payment</button>

            <div class="cytech-requirements">
                <h4>Requirements</h4>
                <ul>
                    <li>✅ Valid Copy of ID</li>
                    <li>✅ MPESA / Bank statement</li>
                    <li>✅ Deposit as shown below</li>
                </ul>
            </div>
        </div>

        <!-- Modal -->
        <div id="paymentModal" class="payment-modal">
            <div class="modal-content">
                <div class="modal-scroll">
                    <span id="closeModal" class="close-btn">&times;</span>

                    <div id="modalPlanSection" class="modal-plan-section">
                        <label for="simplePlan">Select Payment Plan:</label>
                        <select id="simplePlan">
                            <option value="">Choose your payment plan</option>';

        // Add payment plans from settings
        foreach ($payment_plans as $plan) {
            $value = $plan['weeks'] . ',' . $plan['interest'] . ',' . $plan['deposit'];
            $label = $plan['weeks'] . ' weeks - ' . $plan['interest'] . '% interest - ' . $plan['deposit'] . '% deposit';
            $calculator_html .= '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }

        $calculator_html .= '
                        </select>
                    </div>

                    <div id="resultsSection" class="modal-results">
                        <p id="resultCash"></p>
                        <p id="resultDeposit"></p>
                        <p id="resultBalance"></p>
                        <p id="resultInterest"></p>
                        <p id="resultWeekly"></p>
                        <p id="resultWeeks"></p>
                    </div>

                    <a href="#" id="whatsappBtn" class="whatsapp-btn">💬 Order on WhatsApp</a>
                </div>
            </div>
        </div>
        ';

        return $calculator_html;
    }

    // Add custom price field in product admin (simple products)
    public function add_custom_price_field() {
        global $post;

        // Only show for products in selected categories
        if (!$this->should_show_calculator($post->ID)) {
            return;
        }

        echo '<div class="options_group lipa-polepole-price-section">';
        echo '<p style="padding: 10px; background: #e8f5e9; border-left: 4px solid #4caf50; margin: 10px 0;"><strong>Lipa Polepole Pricing</strong><br><small>Set the original full price. The deposit price (40%) will be calculated automatically.</small></p>';

        woocommerce_wp_text_input(array(
            'id' => '_lipa_polepole_full_price',
            'label' => 'Original Full Price (KSh)',
            'desc_tip' => true,
            'description' => 'The original full price for this product. The regular price will be set to 40% of this value automatically.',
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '0.01',
                'min' => '0'
            ),
            'value' => get_post_meta($post->ID, '_lipa_polepole_full_price', true)
        ));

        // Show calculated deposit price
        $full_price = get_post_meta($post->ID, '_lipa_polepole_full_price', true);
        if ($full_price && $full_price > 0) {
            $deposit_price = $full_price * 0.40;
            echo '<p class="form-field" style="padding-left: 12px; color: #2271b1;"><strong>Calculated Deposit Price: KSh ' . number_format($deposit_price, 2) . '</strong></p>';
        }

        echo '</div>';
    }

    // Save custom price field (simple products)
    public function save_custom_price_field($post_id) {
        // Only process for products in selected categories
        if (!$this->should_show_calculator($post_id)) {
            return;
        }

        if (isset($_POST['_lipa_polepole_full_price'])) {
            $full_price = floatval($_POST['_lipa_polepole_full_price']);

            if ($full_price > 0) {
                // Save the full price
                update_post_meta($post_id, '_lipa_polepole_full_price', $full_price);

                // Calculate and set the deposit price (40%)
                $deposit_price = $full_price * 0.40;

                $product = wc_get_product($post_id);
                if ($product && !$product->is_type('variable')) {
                    $product->set_regular_price($deposit_price);
                    $product->set_price($deposit_price);
                    $product->save();
                }
            }
        }
    }

    // Add custom price field for variations
    public function add_variation_price_field($loop, $variation_data, $variation) {
        // Check if parent product is in selected categories
        $parent_id = wp_get_post_parent_id($variation->ID);
        if (!$this->should_show_calculator($parent_id)) {
            return;
        }

        $full_price = get_post_meta($variation->ID, '_lipa_polepole_full_price', true);

        echo '<div class="lipa-polepole-variation-price">';
        echo '<p style="padding: 10px; background: #e8f5e9; border-left: 4px solid #4caf50; margin: 10px 0;"><strong>Lipa Polepole Pricing</strong></p>';

        woocommerce_wp_text_input(array(
            'id' => '_lipa_polepole_full_price_var[' . $loop . ']',
            'name' => '_lipa_polepole_full_price_var[' . $loop . ']',
            'label' => 'Original Full Price (KSh)',
            'desc_tip' => true,
            'description' => 'The original full price for this variation. The regular price will be set to 40% of this value.',
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '0.01',
                'min' => '0'
            ),
            'value' => $full_price,
            'wrapper_class' => 'form-row form-row-full'
        ));

        // Show calculated deposit price
        if ($full_price && $full_price > 0) {
            $deposit_price = $full_price * 0.40;
            echo '<p class="form-row form-row-full" style="padding-left: 12px; color: #2271b1;"><strong>Calculated Deposit: KSh ' . number_format($deposit_price, 2) . '</strong></p>';
        }

        echo '</div>';
    }

    // Save custom price field for variations
    public function save_variation_price_field($variation_id, $loop) {
        // Check if parent product is in selected categories
        $parent_id = wp_get_post_parent_id($variation_id);
        if (!$this->should_show_calculator($parent_id)) {
            return;
        }

        if (isset($_POST['_lipa_polepole_full_price_var'][$loop])) {
            $full_price = floatval($_POST['_lipa_polepole_full_price_var'][$loop]);

            if ($full_price > 0) {
                // Save the full price
                update_post_meta($variation_id, '_lipa_polepole_full_price', $full_price);

                // Calculate and set the deposit price (40%)
                $deposit_price = $full_price * 0.40;

                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $variation->set_regular_price($deposit_price);
                    $variation->set_price($deposit_price);
                    $variation->save();
                }
            }
        }
    }

    // Register REST API fields
    public function register_rest_fields() {
        // Register field for products
        register_rest_field('product', 'lipa_polepole_full_price', array(
            'get_callback' => array($this, 'get_full_price_rest'),
            'update_callback' => null,
            'schema' => array(
                'description' => 'Original full price for Lipa Polepole calculator',
                'type' => 'number',
                'context' => array('view', 'edit')
            )
        ));

        // Register field for product variations
        register_rest_field('product_variation', 'lipa_polepole_full_price', array(
            'get_callback' => array($this, 'get_full_price_rest'),
            'update_callback' => null,
            'schema' => array(
                'description' => 'Original full price for Lipa Polepole calculator',
                'type' => 'number',
                'context' => array('view', 'edit')
            )
        ));

        // Add deposit price field as well
        register_rest_field('product', 'lipa_polepole_deposit_price', array(
            'get_callback' => array($this, 'get_deposit_price_rest'),
            'update_callback' => null,
            'schema' => array(
                'description' => 'Calculated deposit price (40% of full price)',
                'type' => 'number',
                'context' => array('view', 'edit')
            )
        ));

        register_rest_field('product_variation', 'lipa_polepole_deposit_price', array(
            'get_callback' => array($this, 'get_deposit_price_rest'),
            'update_callback' => null,
            'schema' => array(
                'description' => 'Calculated deposit price (40% of full price)',
                'type' => 'number',
                'context' => array('view', 'edit')
            )
        ));
    }

    // Get full price for REST API
    public function get_full_price_rest($object, $field_name, $request) {
        $product_id = $object['id'];

        // Check if product is in selected categories
        if (!$this->should_show_calculator($product_id)) {
            return null;
        }

        $full_price = get_post_meta($product_id, '_lipa_polepole_full_price', true);
        return $full_price ? floatval($full_price) : null;
    }

    // Get deposit price for REST API
    public function get_deposit_price_rest($object, $field_name, $request) {
        $product_id = $object['id'];

        // Check if product is in selected categories
        if (!$this->should_show_calculator($product_id)) {
            return null;
        }

        $full_price = get_post_meta($product_id, '_lipa_polepole_full_price', true);
        if ($full_price && $full_price > 0) {
            return floatval($full_price) * 0.40;
        }
        return null;
    }
}

// Initialize
new Simple_Calculator_Plugin();