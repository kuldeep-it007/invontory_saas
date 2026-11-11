export default [
    {
        name: 'product.nav_id',
        title: 'ID',
    },
    {
        name: 'purchase_order_number',
        title: 'SO No.',
        callback:function(val){
            return val.replace('PO', 'SO')
        }
    },
    {
        name: 'purchase_order_number',
        title: 'Dealer PO No.',
    },
  	{
    	name: '__slot:category',
    	title: 'Category',
  	},
    {
        name: '__slot:collection',
        title: 'Collection',
    },
    {
        name: '__slot:colour',
        title: 'Colour',
    },
    {
        name: 'batch_no',
        title: 'Batch',
    },
    {
        name: '__slot:customer',
        title: 'Dealer',
    },
    {
        name: '__slot:quantity',
        title: 'Quantity',
    },
    {
        name: 'status',
        title: 'Status',
    },
    {
        name: '__slot:actions',
        title: '',
        titleClass: 'shrink has-actions',
        dataClass: 'shrink has-actions'
    }
];