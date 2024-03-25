pimcore.registerNS("pimcore.plugin.productsyncbundle");

pimcore.plugin.productsyncbundle = Class.create({
    initialize: function () {
        document.addEventListener(pimcore.events.preMenuBuild, this.preMenuBuild.bind(this));
    },

    openIndexPage: function () {
        try {
            pimcore.globalmanager.get('productsync_connector_settings').activate();
        } catch (e) {
            pimcore.globalmanager.add('productsync_connector_settings', new pimcore.tool.genericiframewindow('settings', '/productsync_connector_settings', "pimcore_icon_system", t("configuration")));
        }
    },

    preMenuBuild: function (e) {
        // the event contains the existing menu
        let menu = e.detail.menu;

        const user = pimcore.globalmanager.get('user');
        if(user.isAllowed("magento_product_connector")) {
            let items = [{
                text: t("qburstMagentoConnector"),
                iconCls: 'qburst-magento-productsync-logo', // make sure your icon class exists
                priority: 1, // define the position where you menu should be shown. Core menu items will leave a gap of 10 custom menu items
                itemId: 'pimcore_qburst_productsync', // specify your custom itemId here
                handler: this.openIndexPage, // define a handler what should happen if you click on the menu item
            }];
            // the property name is used as id with the prefix pimcore_menu_ in the html markup e.g. pimcore_menu_dataprivacybundle
            menu.productsyncbundle = {
                label: t('QBurst Bundles'), // set your label here, will be shown as tooltip
                iconCls: "qburst-logo",
                priority: 102, // define the position where you menu should be shown. Core menu items will leave a gap of 10 custom main menu items
                items: items, //if your main menu has subitems please see Adding Custom Submenus To ExistingNavigation Items
                shadow: false,
                noSubmenus: false, // if there are no submenus set to true otherwise menu won't show up
                cls: "pimcore_navigation_flyout", // use pimcore_navigation_flyout if you have subitems
            };
        }
    },

});

var productsyncbundle = new pimcore.plugin.productsyncbundle();
