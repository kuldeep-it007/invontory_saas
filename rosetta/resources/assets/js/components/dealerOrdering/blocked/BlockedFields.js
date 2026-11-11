export default[
	{
		name:"__checkbox",
	},
	{
		name:"product.category.display_name",
		title:"Category"
	},
	{
		name:"product.collection.display_name",
		title:"Collection"
	},
	{
		name:"product.colour.display_name",
		title:"Colour"
	},
	{
		name:"stock_point_name",
		title:"Stock Point"
	},
	{
		name:"batch_no",
		title:"Batch No"
	},
	{
    	name: '__slot:quantity',
    	title: 'Quantity'
  	},
	{
		name:"unit_price",
		title:"RRP"
	},
	{
		name:"discount",
		title:"Discount %",
		callback(val){
			return `${val}`
		}
	},
	{
    	name: '__slot:discounted-price',
    	title: 'Disc. Price'
  	},
  	{
    	name: '__slot:amount',
    	title: 'Net Amount'
  	},
  	{
    	name: '__slot:actions',
    	title: ''
  	}

]