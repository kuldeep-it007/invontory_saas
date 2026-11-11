export default [
	{
		name:"region.name",
		title: "Region",
	},
	{
    	name: '__slot:customer-type',
    	title: 'Cust Type',
  	},
	{
    	name: '__slot:customer',
    	title: 'Customer',
  	},
  	{
    	name: 'category.code',
    	title: 'Category',
  	},
  	{
    	name: 'collection.code',
    	title: 'Collection',
  	},
  	{
    	name: 'colour.code',
    	title: 'Colour',
  	},
  	{
    	name: 'state.name',
    	title: 'State',
  	},
  	{
    	name: 'city',
    	title: 'City',
  	},
  	{
    	name: 'post_code',
    	title: 'Post Code',
  	},
  	{
    	name: 'discount',
    	title: 'Discount',
    	callback:function(value){
    		return `${value}%`
    	}
  	},
	{
    	name: '__slot:actions',
    	title: '',
    	titleClass: 'text-right has-actions',
    	dataClass: 'text-right has-actions'
  	}
];