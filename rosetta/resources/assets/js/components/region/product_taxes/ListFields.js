export default [
   	{
    	name: 'category_code',
    	title: 'Category',
  	},
    {
        name: 'collection_code',
        title: 'Collection',
    },
    {
      name: 'tax_type_code',
      title: 'Tax Type',
    },
    {
        name: 'tax_percent',
        title: 'Tax (%)',
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