export default[
    {
        name:"__checkbox",
    },
    {
        name: '__slot:customer',
        title: 'Customer',
    },
    {
        name: '__slot:productName',
        title: 'Product'
    },
    {
        name: '__slot:order_quantity',
        title: 'Quantity <span class="label label-default pull-right">RL</span>'
    },
    {
        name:"unit_price",
        title:"RRP"
    },
    {
        name:"__slot:discount",
        title:"Discount %"
    },
    {
        name: '__slot:discounted_price',
        title: 'Disc. Price'
    },
    {
        name: '__slot:amount',
        title: 'Net Amount'
    },
    {
        name: '__slot:tax_amount',
        title: 'Tax Amount'
    },
    {
        name: '__slot:net_amount',
        title: 'Total Amount'
    },
    {
        name:"created_at",
        title:"Added On",
        callback:function(val){
            return moment.unix(val).format('DD MMM YYYY')
        },
        titleClass: "shrink",
        dataClass:"shrink",
    },
    {
        name: 'expires',
        title: 'Discount Validity',
        callback:function(val){
            return val ? moment.unix(val).format('DD MMM YYYY') : null
        },
        titleClass: "shrink",
        dataClass:"shrink",
    },
    {
        name:"eta",
        title:"ETA",
        callback:function(val){
            return val ? moment.unix(val).format('DD MMM YYYY') : null
        },
        titleClass: "shrink",
        dataClass:"shrink"
    },
    {
        name: '__slot:actions',
        title: '',
        titleClass: "shrink",
        dataClass:"shrink actions"
    }
]