export default [
    {
      name:"nav_id",
      title:"NAV. ID",
      dataClass:"shrink",
      titleClass:"shrink"
    },
    {
      name:"name",
      title:"Name",
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
    	name: 'price.unit',
    	title: 'MUP',
    	callback:function(value){
    		return `${value}`
    	}
  	},
	{
    	name: '__slot:actions',
    	title: '',
    	titleClass: 'text-right has-actions',
    	dataClass: 'text-right has-actions'
  	}
];