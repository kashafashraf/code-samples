$(document).ready(function(){
   $("#price-search li").click(function(){
      $(this).toggleClass("active");
      showResults("","");
   });
  
   $("#recipient-search li").click(function(){
      $(this).toggleClass("active");
      showResults("","");
   });
  
   $("#category-search li").click(function(){
      //alert('i m in toggle'); 
      $(this).toggleClass("active");
      showResults("","");
   });


 });

Array.prototype.in_array = function(p_val) {
	for(var i = 0, l = this.length; i < l; i++) {
		if(this[i] == p_val) {
			return true;
		}
	}
	return false;
}
 
function showResults(sort_by, items_per_page,page_no)
{
    var price_type = [];
    var recipient_type= [];
    var category_type= [];

    var price="";
    var recipient="";
    var category="";


    //Get selected price-ranges and create a string for query string
    var price_count=0;
    $("#price-search .active").each(function(){
      price_type[price_count] = $(this).text(); 
      price_count++;
    });
    price = implode('~', price_type);
    price =escape(price);
    price = price.replace(/%A3/g, "|");
    
    //Get selected recipient and create a string for query string
    var recipient_count=0;
    $("#recipient-search .active").each(function(){
      recipient_type[recipient_count] = $(this).text(); 
      recipient_count++;
    });
    recipient = implode('~', recipient_type);
    recipient =escape(recipient);
    recipient = recipient.replace(/%A3/g, "|");
    
    //Get selected categories and create a string for query string
    var main_category=""
    var category_count=0;
    
        var cat_array = ["all watches","all gold","all silver jewellery","all diamonds","all shamballa ","all childrens","all trollbeads "];
    $("#category-search .active").each(function(){
       
      if(cat_array.in_array($(this).text()))
        category_type[category_count] = main_category;
      else
        category_type[category_count] = $(this).text();          
        
      category_count++;
    });
    category = implode('~', category_type);
    category =escape(category);
    category = category.replace(/%A3/g, "|");

    if(category=="")
      category = '';

    $("#col-centre").load(url + "showsearchresults.inc.php?price="+escape(price)+"&recipient="+escape(recipient)+"&category="+escape(category)+"&sort_by="+sort_by+"&records_per_page="+items_per_page+"&page="+page_no); 

}