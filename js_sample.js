/**
 * Created by Mirza on 24/05/2015.
 */

$(function () {
    var App = window;
    var Order = App.Order = {
        sections: {
            category: {
                accordionGroupCategory: $('#accordion-group-category'),
                accordionHeadingCategory: $('#accordion-heading-category'),
                selectedCategory: $('#selected-category'),
                categoryItem: $('.cat_list_item'),
                catImage: $('.cat_img'),
                selectedVal: null,
                onCategoryClick: function () {
                    Order.sections.products.hideFlashMessage();
                    var section = Order.sections.category;
                    section.categoryItem = $(this);
                    section.selectedCategory.text(section.categoryItem.find('label').text());
                    section.catImage.removeClass('image-selected');
                    section.categoryItem.find('.cat_img').addClass('image-selected');
                    Order.sections.subcategory.accordionGroupSubCategory.addClass('display-none');
                    Order.sections.products.accordionGroupProducts.addClass('display-none');
                    // populate sub-categories
                    section.selectedVal = section.categoryItem.attr('data-val');
                    Order.sections.request.getSubCategories(section.categoryItem.attr('data-val'));
                    scrollToElement(Order.sections.category.accordionHeadingCategory, 0);
                    return false;
                },
                onHeaderClick: function() {
                    $('#collapseCategory').collapse('show');
                    $('#collapseSubCategory').collapse('hide');
                    //scrollToElement(Order.sections.subcategory.accordionHeadingSubCategory, 0);
                },
                init: function () {
                    this.categoryItem.click(this.onCategoryClick);
                    this.accordionHeadingCategory.click(this.onHeaderClick);
                }
            },
            subcategory:{
                accordionGroupSubCategory: $('#accordion-group-subcategory'),
                accordionHeadingSubCategory: $('#accordion-heading-sub-category'),
                selectedSubCategory: $('#selected-sub-category'),
                subCategoryItemContainer: $('#collapseSubCategory'),
                subCategoryItem: $('.sub_cat_list_item'),
                subCatImage: $('.sub_cat_img'),
                selectedVal: null,
                onSubCategoryClick: function () {
                    Order.sections.products.hideFlashMessage();
                    var section = Order.sections.subcategory;
                    section.subCategoryItem = $(this);
                    section.selectedSubCategory.text(section.subCategoryItem.find('label').text());
                    section.subCategoryItemContainer.find('.sub_cat_img').removeClass('image-selected');
                    section.subCategoryItem.find('.sub_cat_img').addClass('image-selected');
                    // populate sub-categories
                    Order.sections.request.getProducts(Order.sections.category.selectedVal, section.subCategoryItem.attr('data-val'));
                    scrollToElement(Order.sections.subcategory.accordionHeadingSubCategory, 0);
                    return false;

                },
                onHeaderClick: function() {
                    //$('#collapseCategory').collapse('show');
                    //$('#collapseSubCategory').collapse('hide');
                    //scrollToElement(Order.sections.subcategory.accordionHeadingSubCategory, 0);
                },
                init: function () {
                    var section = this;
                    section.subCategoryItemContainer.on('click', '.sub_cat_list_item', section.onSubCategoryClick);
                        //.click(this.onSubCategoryClick);
                    //this.accordionHeadingCategory.click(this.onHeaderClick);
                }

            },
            
        init: function () {
            $.each(this.sections, function (k, v) {
                if (v && 'object' == typeof v && 'function' == typeof v.init) {
                    v.init();
                }
            });
        }
    };
    Order.init();
});
