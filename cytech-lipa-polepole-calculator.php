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
    }

    public function enqueue_scripts() {
        if (!is_product()) {
            return;
        }

        wp_enqueue_script('jquery');

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
                            variationsData[variation.variation_id] = {
                                price: variation.display_price,
                                attributes: variation.attributes,
                                variation_id: variation.variation_id
                            };
                        }
                    });
                }
            }

            // Function to get current product price
            function getCurrentPrice() {
                // PRIORITY 1: For variable products, use the price from modal variation selector
                if (isVariableProduct && currentSelectedPrice > 0) {
                    return currentSelectedPrice;
                }

                // PRIORITY 2: Try to get from variation price display on page
                var variationPrice = $(".summary .woocommerce-variation-price .woocommerce-Price-amount bdi, .entry-summary .woocommerce-variation-price .woocommerce-Price-amount bdi").first();
                if (variationPrice.length && variationPrice.text()) {
                    var price = parseFloat(variationPrice.text().replace(/[^0-9.-]+/g,""));
                    if (price > 0) return price;
                }

                // PRIORITY 3: Get regular product price from summary area
                var priceElement = $(".summary .price .woocommerce-Price-amount bdi, .entry-summary .price .woocommerce-Price-amount bdi").first();
                if (priceElement.length) {
                    var price = parseFloat(priceElement.text().replace(/[^0-9.-]+/g,""));
                    if (price > 0) return price;
                }

                // PRIORITY 4: Fallback for sale prices
                var salePrice = $(".summary .price ins .amount bdi, .entry-summary .price ins .amount bdi").first();
                if (salePrice.length) {
                    var price = parseFloat(salePrice.text().replace(/[^0-9.-]+/g,""));
                    if (price > 0) return price;
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

                var whatsappUrl = "https://wa.me/254726166061?text=" + whatsappMessage;
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

                    // Store the selected variation price
                    currentSelectedPrice = parseFloat(variation.price);

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

        global $product;

        // Check if in correct category
        $term = get_term_by('slug', 'lipa-polepole-iphones', 'product_cat');
        if (!$term || !has_term($term->term_id, 'product_cat', $product->get_id())) {
            return $content;
        }

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
                            <option value="">Choose your payment plan</option>
                            <option value="2,25,40">2 weeks - 25% interest - 40% deposit</option>
                            <option value="4,30,40">4 weeks - 30% interest - 40% deposit</option>
                            <option value="8,40,40">8 weeks - 40% interest - 40% deposit</option>
                            <option value="12,50,40">12 weeks - 50% interest - 40% deposit</option>
                            <option value="16,60,50">16 weeks - 60% interest - 50% deposit</option>
                            <option value="20,70,50">20 weeks - 70% interest - 50% deposit</option>
                            <option value="24,80,50">24 weeks - 80% interest - 50% deposit</option>
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
}

// Initialize
new Simple_Calculator_Plugin();