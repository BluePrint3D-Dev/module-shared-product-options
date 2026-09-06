/**
 * Copyright (c) 2026 BluePrint3D Ltd. All rights reserved.
 *
 * This software is provided free of charge for personal or commercial use.
 * Resale, redistribution, or sublicensing of this source code, modified or
 * unmodified, for direct financial gain is strictly prohibited.
 *
 * @author    BluePrint3D Ltd <support@blueprint3d.dev>
 * @copyright 2026 BluePrint3D Ltd (Company No. 13473806)
 * @license   Custom Proprietary EULA (See LICENSE.txt)
 */
define([
    'jquery',
    'domReady!'
], function ($) {
    'use strict';

    return function () {
        var $placeholder = $('[data-gallery-role="gallery-placeholder"]');

        if (!$placeholder.length || !window.b3dGalleryFallbackConfig) {
            return;
        }

        /**
         * Detect whether Magento's own gallery widget already initialized on this element.
         *
         * @returns {Boolean}
         */
        function isInitialized() {
            return !!$placeholder.data('gallery') || $placeholder.find('.fotorama').length > 0;
        }

        // Give the native mage-init bootstrap a real chance to run first; only step in
        // if it demonstrably never initialized the widget.
        setTimeout(function () {
            if (isInitialized()) {
                return;
            }

            var config = $.extend(true, {}, window.b3dGalleryFallbackConfig),
                mixins = config.mixins || [];

            delete config.mixins;

            require(['mage/gallery/gallery'].concat(mixins), function (Gallery) {
                var mixinResults = Array.prototype.slice.call(arguments, 1),
                    element = $placeholder.get(0);

                if (isInitialized()) {
                    return;
                }

                mixinResults.forEach(function (mixin) {
                    $.extend(true, config, mixin(config, element));
                });

                // Matches how Magento's own mage/apply/main.js invokes this component:
                // the module is a Class constructor called directly, not `new`-ed.
                Gallery(config, element);
            });
        }, 2500);
    };
});
