/**
 * Marketing Landing Page & Interactive Bundle Configurator JavaScript
 */

(function () {
  "use strict";

  // Elements
  var pricingSwitchBtns = document.querySelectorAll(".switch-btn");
  var predefinedView = document.getElementById("predefined-bundles-view");
  var customView = document.getElementById("custom-bundle-view");

  var customCheckboxes = document.querySelectorAll(".calc-module-checkbox");
  var calcItemsList = document.getElementById("calc-selected-items");
  var calcSubtotalEl = document.getElementById("calc-subtotal-val");
  var calcDiscountEl = document.getElementById("calc-discount-val");
  var calcDiscountRow = document.getElementById("calc-discount-row");
  var calcTotalEl = document.getElementById("calc-total-val");
  var calcSavingsBadge = document.getElementById("calc-savings-badge");
  var calcBuyBtn = document.getElementById("calc-buy-btn");

  // Modal elements
  var modal = document.getElementById("checkout-modal");
  var modalCloseBtn = document.getElementById("modal-close-btn");
  var modalItemTitle = document.getElementById("modal-item-title");
  var modalItemPrice = document.getElementById("modal-item-price");
  var modalItemList = document.getElementById("modal-item-list");
  var modalPlanBadge = document.getElementById("modal-plan-badge");
  var modalItemsCount = document.getElementById("modal-items-count");
  var modalCalcBreakdown = document.getElementById("modal-calc-breakdown");
  var modalCalcRegularRow = document.getElementById("modal-calc-regular-row");
  var modalCalcOriginal = document.getElementById("modal-calc-original");
  var modalCalcDiscountRow = document.getElementById("modal-calc-discount-row");
  var modalCalcDiscount = document.getElementById("modal-calc-discount");
  var modalCalcFinal = document.getElementById("modal-calc-final");
  var modalBtnPrice = document.getElementById("modal-btn-price");
  var modalForm = document.getElementById("modal-checkout-form");
  var modalBundleInput = document.getElementById("modal-bundle-input");
  var modalProductInput = document.getElementById("modal-product-input");
  var modalModulesInput = document.getElementById("modal-modules-input");
  var modalIncludeCoreInput = document.getElementById("modal-include-core-input");

  var toggleBuilderBtn = document.getElementById("toggle-custom-builder-btn");
  var customBuilderWrapper = document.getElementById("custom-builder-wrapper");

  if (toggleBuilderBtn && customBuilderWrapper) {
    toggleBuilderBtn.addEventListener("click", function () {
      if (customBuilderWrapper.style.display === "none" || !customBuilderWrapper.style.display) {
        customBuilderWrapper.style.display = "block";
        toggleBuilderBtn.innerHTML = "▲ Hide Custom Bundle Builder";
        updateCustomBundleCalculation();
      } else {
        customBuilderWrapper.style.display = "none";
        toggleBuilderBtn.innerHTML = "🛠️ Need specific modules? Click to build custom bundle &rarr;";
      }
    });
  }

  // Interactive Custom Bundle Calculation
  function updateCustomBundleCalculation() {
    if (!customCheckboxes || customCheckboxes.length === 0) return;

    var corePrice = 49.0;
    var subtotal = 0.0;
    var selectedModules = [];
    var selectedNames = [];

    customCheckboxes.forEach(function (chk) {
      var row = chk.closest(".calc-module-row");
      var price = parseFloat(chk.getAttribute("data-price") || 0);
      var slug = chk.getAttribute("data-slug");
      var name = chk.getAttribute("data-name");

      if (chk.checked) {
        if (row) row.classList.add("selected");
        subtotal += price;
        if (slug !== "core") {
          selectedModules.push(slug);
          selectedNames.push(name);
        } else {
          selectedNames.push(name);
        }
      } else {
        if (row) row.classList.remove("selected");
      }
    });

    // Dynamic discounts and currency from Central License Manager
    var dConfig = (window.LANDING_DATA && window.LANDING_DATA.discounts) || { tier_1: 10, tier_2: 15, tier_3: 20 };
    var sym = (window.LANDING_DATA && window.LANDING_DATA.branding && window.LANDING_DATA.branding.currency_symbol) || "$";

    var discountRate = 0.0;
    if (selectedModules.length >= 3) {
      discountRate = (dConfig.tier_3 || 20) / 100;
    } else if (selectedModules.length >= 2) {
      discountRate = (dConfig.tier_2 || 15) / 100;
    } else if (selectedModules.length === 1) {
      discountRate = (dConfig.tier_1 || 10) / 100;
    }

    var discountAmount = subtotal * discountRate;
    var totalAmount = subtotal - discountAmount;

    // Update UI elements
    if (calcSubtotalEl) {
      calcSubtotalEl.textContent = sym + subtotal.toFixed(2);
    }

    if (calcDiscountRow && calcDiscountEl) {
      if (discountAmount > 0) {
        calcDiscountRow.style.display = "flex";
        calcDiscountEl.textContent = "-" + sym + discountAmount.toFixed(2) + " (" + Math.round(discountRate * 100) + "% off)";
      } else {
        calcDiscountRow.style.display = "none";
      }
    }

    if (calcTotalEl) {
      calcTotalEl.textContent = sym + totalAmount.toFixed(2);
    }

    if (calcSavingsBadge) {
      if (discountAmount > 0) {
        calcSavingsBadge.style.display = "inline-block";
        calcSavingsBadge.textContent = "🎉 Bundle Discount: Save " + sym + discountAmount.toFixed(2);
      } else {
        calcSavingsBadge.style.display = "none";
      }
    }

    if (calcItemsList) {
      calcItemsList.innerHTML = "";
      selectedNames.forEach(function (n) {
        var li = document.createElement("li");
        li.style.cssText = "font-size:12px;color:#cbd5e1;display:flex;align-items:center;gap:6px;margin-bottom:4px;";
        li.innerHTML = '<span style="color:#818cf8;font-size:11px;">✦</span> ' + n;
        calcItemsList.appendChild(li);
      });
    }

    if (calcBuyBtn) {
      calcBuyBtn.setAttribute("data-amount", totalAmount.toFixed(2));
      calcBuyBtn.setAttribute("data-price", totalAmount.toFixed(2));
      calcBuyBtn.setAttribute("data-modules", selectedModules.join(","));
      calcBuyBtn.setAttribute("data-items", selectedNames.join(", "));
      calcBuyBtn.innerHTML = "Proceed to Buy Bundle (" + sym + totalAmount.toFixed(2) + ") &rarr;";
    }
  }

  // Bind checkbox clicks
  if (customCheckboxes) {
    customCheckboxes.forEach(function (chk) {
      chk.addEventListener("change", updateCustomBundleCalculation);
      var row = chk.closest(".calc-module-row");
      if (row) {
        row.addEventListener("click", function (e) {
          if (e.target !== chk) {
            chk.checked = !chk.checked;
            updateCustomBundleCalculation();
          }
        });
      }
    });
  }

  // Helper to create an itemized product row for checkout modal
  function createModalProductRow(badgeText, isCore, name, price, sym, cur) {
    var itemRow = document.createElement("div");
    itemRow.className = "modal-product-item";

    var leftDiv = document.createElement("div");
    leftDiv.className = "item-left";

    var tagSpan = document.createElement("span");
    tagSpan.className = "badge-tag " + (isCore ? "tag-core" : "tag-mod");
    tagSpan.textContent = badgeText;

    var nameSpan = document.createElement("span");
    nameSpan.className = "item-name";
    nameSpan.textContent = name;
    nameSpan.title = name;

    leftDiv.appendChild(tagSpan);
    leftDiv.appendChild(nameSpan);

    var priceDiv = document.createElement("div");
    priceDiv.className = "item-price";
    priceDiv.textContent = sym + parseFloat(price).toFixed(2) + " " + (cur || "USD");

    itemRow.appendChild(leftDiv);
    itemRow.appendChild(priceDiv);
    return itemRow;
  }

  // Buy buttons & Modal Trigger
  document.querySelectorAll(".open-checkout-btn").forEach(function (btn) {
    btn.addEventListener("click", function (e) {
      e.preventDefault();

      var type = btn.getAttribute("data-type"); // 'bundle', 'product', or 'custom'
      var slug = btn.getAttribute("data-slug") || "";
      var title = btn.getAttribute("data-title") || "Software License";
      var price = parseFloat(btn.getAttribute("data-price") || 0);
      var items = btn.getAttribute("data-items") || "";
      var modules = btn.getAttribute("data-modules") || "";

      var branding = (window.LANDING_DATA && window.LANDING_DATA.branding) || {};
      var sym = branding.currency_symbol || "$";
      var cur = branding.currency || "USD";

      if (modalItemList) modalItemList.innerHTML = "";

      if (type === "bundle") {
        var bundleData = (window.LANDING_DATA && window.LANDING_DATA.bundle_details && window.LANDING_DATA.bundle_details[slug]) || null;

        if (bundleData && Array.isArray(bundleData.items) && bundleData.items.length > 0) {
          if (modalItemTitle) modalItemTitle.textContent = bundleData.name;
          if (modalPlanBadge) modalPlanBadge.textContent = "Enterprise Bundle";
          if (modalItemsCount) {
            modalItemsCount.style.display = "inline-block";
            modalItemsCount.textContent = bundleData.items.length + " Products Included";
          }

          bundleData.items.forEach(function (it) {
            var badgeText = it.is_core ? "[CORE]" : "[MODULE]";
            var row = createModalProductRow(badgeText, it.is_core, it.name, it.price, sym, it.currency || cur);
            modalItemList.appendChild(row);
          });

          var regSum = parseFloat(bundleData.regular_sum || bundleData.price);
          var bPrice = parseFloat(bundleData.price || price);
          var savings = parseFloat(bundleData.savings || (regSum - bPrice));
          var discPct = bundleData.discount_pct || (regSum > 0 ? Math.round((savings / regSum) * 100) : 0);

          if (savings > 0) {
            if (modalCalcRegularRow) modalCalcRegularRow.style.display = "flex";
            if (modalCalcOriginal) modalCalcOriginal.textContent = sym + regSum.toFixed(2) + " " + cur;
            if (modalCalcDiscountRow) modalCalcDiscountRow.style.display = "flex";
            if (modalCalcDiscount) modalCalcDiscount.textContent = "-" + sym + savings.toFixed(2) + " (" + discPct + "% OFF)";
          } else {
            if (modalCalcRegularRow) modalCalcRegularRow.style.display = "none";
            if (modalCalcDiscountRow) modalCalcDiscountRow.style.display = "none";
          }

          if (modalCalcFinal) modalCalcFinal.textContent = sym + bPrice.toFixed(2) + " " + cur;
          if (modalBtnPrice) modalBtnPrice.textContent = sym + bPrice.toFixed(2);
          if (modalItemPrice) modalItemPrice.textContent = sym + bPrice.toFixed(2);
        } else {
          // Fallback if not found in bundle_details
          if (modalItemTitle) modalItemTitle.textContent = title;
          if (modalPlanBadge) modalPlanBadge.textContent = "Software Bundle";
          if (modalItemsCount) modalItemsCount.style.display = "none";
          if (modalCalcRegularRow) modalCalcRegularRow.style.display = "none";
          if (modalCalcDiscountRow) modalCalcDiscountRow.style.display = "none";
          if (modalCalcFinal) modalCalcFinal.textContent = sym + price.toFixed(2) + " " + cur;
          if (modalBtnPrice) modalBtnPrice.textContent = sym + price.toFixed(2);
          if (modalItemPrice) modalItemPrice.textContent = sym + price.toFixed(2);
        }
      } else if (type === "custom") {
        if (modalItemTitle) modalItemTitle.textContent = "Custom Module Bundle";
        if (modalPlanBadge) modalPlanBadge.textContent = "Custom Configuration";

        var customItems = [];
        var customSubtotal = 0;
        if (customCheckboxes) {
          customCheckboxes.forEach(function (chk) {
            if (chk.checked) {
              var cSlug = chk.getAttribute("data-slug");
              var cName = chk.getAttribute("data-name");
              var cPrice = parseFloat(chk.getAttribute("data-price") || 0);
              var isCore = (cSlug === "core");
              customItems.push({
                badge: isCore ? "[CORE]" : "[MODULE]",
                isCore: isCore,
                name: cName,
                price: cPrice
              });
              customSubtotal += cPrice;
            }
          });
        }

        if (modalItemsCount) {
          modalItemsCount.style.display = "inline-block";
          modalItemsCount.textContent = customItems.length + " Products Selected";
        }

        customItems.forEach(function (it) {
          var row = createModalProductRow(it.badge, it.isCore, it.name, it.price, sym, cur);
          modalItemList.appendChild(row);
        });

        var finalAmount = price;
        var discountAmount = customSubtotal - finalAmount;
        if (discountAmount > 0.01) {
          var discPct = Math.round((discountAmount / customSubtotal) * 100);
          if (modalCalcRegularRow) modalCalcRegularRow.style.display = "flex";
          if (modalCalcOriginal) modalCalcOriginal.textContent = sym + customSubtotal.toFixed(2) + " " + cur;
          if (modalCalcDiscountRow) modalCalcDiscountRow.style.display = "flex";
          if (modalCalcDiscount) modalCalcDiscount.textContent = "-" + sym + discountAmount.toFixed(2) + " (" + discPct + "% OFF)";
        } else {
          if (modalCalcRegularRow) modalCalcRegularRow.style.display = "none";
          if (modalCalcDiscountRow) modalCalcDiscountRow.style.display = "none";
        }

        if (modalCalcFinal) modalCalcFinal.textContent = sym + finalAmount.toFixed(2) + " " + cur;
        if (modalBtnPrice) modalBtnPrice.textContent = sym + finalAmount.toFixed(2);
        if (modalItemPrice) modalItemPrice.textContent = sym + finalAmount.toFixed(2);
      } else {
        // Single module / product
        if (modalItemTitle) modalItemTitle.textContent = title;
        if (modalPlanBadge) modalPlanBadge.textContent = "Add-On Module";
        if (modalItemsCount) {
          modalItemsCount.style.display = "inline-block";
          modalItemsCount.textContent = "1 Module";
        }

        var isCore = (slug === "core" || slug === "core_saas");
        var row = createModalProductRow(isCore ? "[CORE]" : "[MODULE]", isCore, title, price, sym, cur);
        modalItemList.appendChild(row);

        if (modalCalcRegularRow) modalCalcRegularRow.style.display = "none";
        if (modalCalcDiscountRow) modalCalcDiscountRow.style.display = "none";
        if (modalCalcFinal) modalCalcFinal.textContent = sym + price.toFixed(2) + " " + cur;
        if (modalBtnPrice) modalBtnPrice.textContent = sym + price.toFixed(2);
        if (modalItemPrice) modalItemPrice.textContent = sym + price.toFixed(2);
      }

      // Set hidden inputs
      if (modalBundleInput) modalBundleInput.value = (type === "bundle") ? slug : "";
      if (modalProductInput) modalProductInput.value = (type === "product") ? slug : "";
      if (modalModulesInput) modalModulesInput.value = (type === "custom") ? modules : "";
      if (modalIncludeCoreInput) modalIncludeCoreInput.value = (type === "custom") ? "1" : "0";

      // Show modal
      if (modal) modal.classList.add("open");
    });
  });

  // Live Demo Hub Modal
  var demoHubModal = document.getElementById("demo-hub-modal");
  var demoHubCloseBtn = document.getElementById("demo-hub-modal-close-btn");

  document.querySelectorAll(".open-demo-hub-btn").forEach(function (btn) {
    btn.addEventListener("click", function (e) {
      if (demoHubModal) {
        e.preventDefault();
        demoHubModal.classList.add("open");
      }
    });
  });

  // Close Checkout Modal
  if (modalCloseBtn && modal) {
    modalCloseBtn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      modal.classList.remove("open");
    });
  }

  if (modal) {
    modal.addEventListener("click", function (e) {
      if (e.target === modal) {
        modal.classList.remove("open");
      }
    });
  }

  // Close Live Demo Hub Modal
  if (demoHubCloseBtn && demoHubModal) {
    demoHubCloseBtn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      demoHubModal.classList.remove("open");
    });
  }

  if (demoHubModal) {
    demoHubModal.addEventListener("click", function (e) {
      if (e.target === demoHubModal) {
        demoHubModal.classList.remove("open");
      }
    });
  }

  // Delegated close listener for any .modal-close buttons
  document.querySelectorAll(".modal-close").forEach(function (btn) {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      var parentModal = btn.closest(".modal-backdrop");
      if (parentModal) {
        parentModal.classList.remove("open");
      }
    });
  });

  // Global Escape Key to close open modals
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      if (modal && modal.classList.contains("open")) modal.classList.remove("open");
      if (demoHubModal && demoHubModal.classList.contains("open")) demoHubModal.classList.remove("open");
    }
  });

  // FAQ Accordion
  document.querySelectorAll(".faq-question").forEach(function (q) {
    q.addEventListener("click", function () {
      var item = q.closest(".faq-item");
      if (item) {
        var isOpen = item.classList.contains("open");
        document.querySelectorAll(".faq-item").forEach(function (i) { i.classList.remove("open"); });
        if (!isOpen) {
          item.classList.add("open");
        }
      }
    });
  });

  // ================= Theme Switcher (Dark / Light) =================
  var themeToggleBtn = document.getElementById("theme-toggle-btn");
  function updateThemeIcons(theme) {
    var sun = document.querySelector(".theme-icon-sun");
    var moon = document.querySelector(".theme-icon-moon");
    if (sun && moon) {
      if (theme === "light") {
        sun.style.display = "inline-block";
        moon.style.display = "none";
      } else {
        sun.style.display = "none";
        moon.style.display = "inline-block";
      }
    }
  }

  var currentTheme = document.documentElement.getAttribute("data-theme") || "dark";
  updateThemeIcons(currentTheme);

  if (themeToggleBtn) {
    themeToggleBtn.addEventListener("click", function () {
      var cur = document.documentElement.getAttribute("data-theme") || "dark";
      var next = cur === "dark" ? "light" : "dark";
      document.documentElement.setAttribute("data-theme", next);
      try {
        localStorage.setItem("pos_marketing_theme", next);
      } catch (err) {}
      updateThemeIcons(next);
    });
  }

  // ================= Language Dropdown Toggle =================
  var langMenuBtn = document.getElementById("lang-menu-btn");
  var langDropdownMenu = document.getElementById("lang-dropdown-menu");
  var langDropdownWrapper = document.getElementById("lang-dropdown-wrapper");

  if (langMenuBtn && langDropdownMenu) {
    langMenuBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      var isOpen = langDropdownMenu.classList.contains("is-open") || langDropdownMenu.style.display === "block";
      if (isOpen) {
        langDropdownMenu.classList.remove("is-open");
        langDropdownMenu.style.display = "none";
        langMenuBtn.setAttribute("aria-expanded", "false");
      } else {
        langDropdownMenu.classList.add("is-open");
        langDropdownMenu.style.display = "block";
        langMenuBtn.setAttribute("aria-expanded", "true");
      }
    });

    document.addEventListener("click", function (e) {
      if (langDropdownWrapper && !langDropdownWrapper.contains(e.target)) {
        langDropdownMenu.classList.remove("is-open");
        langDropdownMenu.style.display = "none";
        langMenuBtn.setAttribute("aria-expanded", "false");
      }
    });
  }

  // ================= Mobile Menu Toggle =================
  var mobileToggleBtn = document.getElementById("mobile-menu-toggle");
  var navLinks = document.querySelector(".nav-links");

  if (mobileToggleBtn && navLinks) {
    mobileToggleBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      var isOpen = navLinks.classList.toggle("is-open");
      mobileToggleBtn.classList.toggle("is-active", isOpen);
      mobileToggleBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });

    document.addEventListener("click", function (e) {
      if (!navLinks.contains(e.target) && !mobileToggleBtn.contains(e.target)) {
        navLinks.classList.remove("is-open");
        mobileToggleBtn.classList.remove("is-active");
        mobileToggleBtn.setAttribute("aria-expanded", "false");
      }
    });

    navLinks.querySelectorAll("a, button").forEach(function (link) {
      link.addEventListener("click", function () {
        navLinks.classList.remove("is-open");
        mobileToggleBtn.classList.remove("is-active");
        mobileToggleBtn.setAttribute("aria-expanded", "false");
      });
    });
  }

  // ================= Palette Presets Switcher (1-Click) =================
  var paletteMenuBtn = document.getElementById("palette-menu-btn");
  var paletteDropdownMenu = document.getElementById("palette-dropdown-menu");
  var paletteDropdownWrapper = document.getElementById("palette-dropdown-wrapper");
  var paletteResetBtn = document.getElementById("palette-reset-btn");

  if (paletteMenuBtn && paletteDropdownMenu) {
    paletteMenuBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      var isOpen = paletteDropdownMenu.classList.contains("is-open") || paletteDropdownMenu.style.display === "block";
      if (isOpen) {
        paletteDropdownMenu.classList.remove("is-open");
        paletteDropdownMenu.style.display = "none";
        paletteMenuBtn.setAttribute("aria-expanded", "false");
      } else {
        paletteDropdownMenu.classList.add("is-open");
        paletteDropdownMenu.style.display = "block";
        paletteMenuBtn.setAttribute("aria-expanded", "true");
        if (typeof langDropdownMenu !== "undefined" && langDropdownMenu) {
          langDropdownMenu.classList.remove("is-open");
          langDropdownMenu.style.display = "none";
          if (langMenuBtn) langMenuBtn.setAttribute("aria-expanded", "false");
        }
      }
    });

    document.addEventListener("click", function (e) {
      if (paletteDropdownWrapper && !paletteDropdownWrapper.contains(e.target)) {
        paletteDropdownMenu.classList.remove("is-open");
        paletteDropdownMenu.style.display = "none";
        paletteMenuBtn.setAttribute("aria-expanded", "false");
      }
    });
  }

  function applyPresetClient(presetKey) {
    if (!window.POS_COLOR_PRESETS || !window.POS_COLOR_PRESETS[presetKey]) return;
    var p = window.POS_COLOR_PRESETS[presetKey];

    document.querySelectorAll(".palette-item").forEach(function (btn) {
      if (btn.getAttribute("data-preset") === presetKey) {
        btn.classList.add("is-active");
      } else {
        btn.classList.remove("is-active");
      }
    });

    var dynStyle = document.getElementById("pos-dynamic-preset-style");
    if (!dynStyle) {
      dynStyle = document.createElement("style");
      dynStyle.id = "pos-dynamic-preset-style";
      document.head.appendChild(dynStyle);
    }

    var secMap = {
      hero: ".hero-section",
      category_strip: ".category-strip-section",
      features: ".control-section",
      business_types: ".business-types-section",
      industries: ".industries-section",
      modules: ".modules-overview-section",
      demos: ".demos-section",
      pricing: ".pricing-showcase-section",
      why_us: ".why-us-section",
      ecosystem: ".ecosystem-section",
      cta_banner: ".cta-banner-section",
      faq: ".faq-section",
      footer: ".footer"
    };

    var css = "";
    css += ":root { --accent-color: " + p.accent + " !important; --cta-gradient: " + p.cta_gradient + " !important; }\n";

    // Dark theme overrides
    css += '[data-theme="dark"] {\n';
    css += '  --bg-dark: ' + p.dark.primary + ' !important;\n';
    css += '  --dark-surface: ' + p.dark.secondary + ' !important;\n';
    css += '  --card-bg: ' + p.dark.card_bg + ' !important;\n';
    css += '  --card-border: ' + p.dark.card_border + ' !important;\n';
    css += '  --text-white: ' + p.dark.text_title + ' !important;\n';
    css += '  --dark-muted: ' + p.dark.text_body + ' !important;\n';
    css += '  --btn-pri-bg: ' + p.accent + ' !important;\n';
    css += '  --btn-sec-bg: ' + p.dark.btn_sec_bg + ' !important;\n';
    css += '  --btn-sec-border: ' + p.dark.btn_sec_border + ' !important;\n';
    css += '  --btn-sec-color: ' + p.dark.btn_sec_color + ' !important;\n';
    css += '  --footer-bg: ' + p.dark.footer_bg + ' !important;\n';
    css += '}\n';
    css += '[data-theme="dark"] .btn-nav-demo, [data-theme="dark"] .btn-hero-demo, [data-theme="dark"] .btn-cta-demo, [data-theme="dark"] .btn-drawer-demo { background: ' + p.dark.btn_sec_bg + ' !important; border-color: ' + p.dark.btn_sec_border + ' !important; color: ' + p.dark.btn_sec_color + ' !important; }\n';
    css += '[data-theme="dark"] .btn-nav-demo:hover, [data-theme="dark"] .btn-hero-demo:hover, [data-theme="dark"] .btn-cta-demo:hover, [data-theme="dark"] .btn-drawer-demo:hover { background: ' + p.dark.btn_sec_border + ' !important; color: ' + p.dark.btn_sec_color + ' !important; }\n';
    css += '[data-theme="dark"] .marketing-app-builder-showcase.ab-mode-theme_matching, [data-theme="dark"] .marketing-app-builder-showcase.ab-mode-theme-matching { background: linear-gradient(180deg, ' + p.dark.secondary + ' 0%, ' + p.dark.primary + ' 100%) !important; border-color: ' + p.dark.card_border + ' !important; }\n';
    css += '[data-theme="dark"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-feature-card, [data-theme="dark"] .marketing-app-builder-showcase.ab-mode-theme-matching .m-ab-feature-card { background: ' + p.dark.card_bg + ' !important; border-color: ' + p.dark.card_border + ' !important; }\n';
    css += '[data-theme="dark"] .price-tier-card, [data-theme="dark"] .how-it-works-card, [data-theme="dark"] .pricing-card { background: ' + p.dark.card_bg + ' !important; border-color: ' + p.dark.card_border + ' !important; }\n';
    css += '[data-theme="dark"] .btn-tier-outline { background: ' + p.dark.btn_sec_bg + ' !important; border-color: ' + p.dark.btn_sec_border + ' !important; color: ' + p.dark.btn_sec_color + ' !important; }\n';
    css += '[data-theme="dark"] .faq-item { background: ' + p.dark.card_bg + ' !important; border-color: ' + p.dark.card_border + ' !important; }\n';

    // Light theme overrides
    css += '[data-theme="light"] {\n';
    css += '  --bg-dark: ' + p.light.primary + ' !important;\n';
    css += '  --dark-surface: ' + p.light.secondary + ' !important;\n';
    css += '  --card-bg: ' + p.light.card_bg + ' !important;\n';
    css += '  --card-border: ' + p.light.card_border + ' !important;\n';
    css += '  --text-white: ' + p.light.text_title + ' !important;\n';
    css += '  --dark-muted: ' + p.light.text_body + ' !important;\n';
    css += '  --btn-pri-bg: ' + p.accent + ' !important;\n';
    css += '  --btn-sec-bg: ' + p.light.btn_sec_bg + ' !important;\n';
    css += '  --btn-sec-border: ' + p.light.btn_sec_border + ' !important;\n';
    css += '  --btn-sec-color: ' + p.light.btn_sec_color + ' !important;\n';
    css += '  --footer-bg: ' + p.light.footer_bg + ' !important;\n';
    css += '}\n';
    css += '[data-theme="light"] .btn-nav-demo, [data-theme="light"] .btn-hero-demo, [data-theme="light"] .btn-cta-demo, [data-theme="light"] .btn-drawer-demo { background: ' + p.light.btn_sec_bg + ' !important; border-color: ' + p.light.btn_sec_border + ' !important; color: ' + p.light.btn_sec_color + ' !important; }\n';
    css += '[data-theme="light"] .btn-nav-demo:hover, [data-theme="light"] .btn-hero-demo:hover, [data-theme="light"] .btn-cta-demo:hover, [data-theme="light"] .btn-drawer-demo:hover { background: ' + p.light.btn_sec_border + ' !important; color: ' + p.light.btn_sec_color + ' !important; }\n';
    css += '[data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching, [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme-matching { background: linear-gradient(180deg, #ffffff 0%, ' + p.light.secondary + ' 100%) !important; border-color: ' + p.light.card_border + ' !important; }\n';
    css += '[data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-feature-card, [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme-matching .m-ab-feature-card { background: #ffffff !important; border-color: ' + p.light.card_border + ' !important; }\n';
    css += '[data-theme="light"] .price-tier-card, [data-theme="light"] .how-it-works-card, [data-theme="light"] .pricing-card { background: ' + p.light.card_bg + ' !important; border-color: ' + p.light.card_border + ' !important; }\n';
    css += '[data-theme="light"] .btn-tier-outline { background: ' + p.light.btn_sec_bg + ' !important; border-color: ' + p.light.btn_sec_border + ' !important; color: ' + p.light.btn_sec_color + ' !important; }\n';
    css += '[data-theme="light"] .faq-item { background: ' + p.light.card_bg + ' !important; border-color: ' + p.light.card_border + ' !important; }\n';

    // Section backgrounds
    for (var k in secMap) {
      if (p.sections && p.sections[k]) {
        css += '[data-theme="dark"] ' + secMap[k] + ' { background: ' + p.sections[k].dark + ' !important; }\n';
        css += '[data-theme="light"] ' + secMap[k] + ' { background: ' + p.sections[k].light + ' !important; }\n';
      }
    }

    dynStyle.innerHTML = css;
    try {
      localStorage.setItem("pos_marketing_preset", presetKey);
    } catch(err) {}
  }

  document.querySelectorAll(".palette-item").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var pKey = this.getAttribute("data-preset");
      applyPresetClient(pKey);
      if (paletteDropdownMenu) {
        paletteDropdownMenu.classList.remove("is-open");
        paletteDropdownMenu.style.display = "none";
        if (paletteMenuBtn) paletteMenuBtn.setAttribute("aria-expanded", "false");
      }
    });
  });

  if (paletteResetBtn) {
    paletteResetBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      try {
        localStorage.removeItem("pos_marketing_preset");
      } catch(err) {}
      var dynStyle = document.getElementById("pos-dynamic-preset-style");
      if (dynStyle) dynStyle.remove();
      document.querySelectorAll(".palette-item").forEach(function (btn) {
        btn.classList.remove("is-active");
      });
      if (paletteDropdownMenu) {
        paletteDropdownMenu.classList.remove("is-open");
        paletteDropdownMenu.style.display = "none";
        if (paletteMenuBtn) paletteMenuBtn.setAttribute("aria-expanded", "false");
      }
      location.reload();
    });
  }

  try {
    var savedPreset = localStorage.getItem("pos_marketing_preset");
    if (savedPreset && window.POS_COLOR_PRESETS && window.POS_COLOR_PRESETS[savedPreset]) {
      applyPresetClient(savedPreset);
    }
  } catch(err) {}


  // Init
  updateCustomBundleCalculation();
})();
