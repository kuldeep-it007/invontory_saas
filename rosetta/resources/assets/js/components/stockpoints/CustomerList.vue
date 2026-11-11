<template>
	<div>
		<div class="box box-widget">
			<div class="box-body">
				<div class="row">
					<div class="col-md-12">
						<div class="row" style="margin-bottom: 10px;">
							<div class="col-md-3">
								<div class="form-group" :class="errors.has('customer_type') ? 'has-error': ''">
									<label>Customer Type</label>
									<customer-type 
										v-model="newStockPoint.customer_type" 
										:allow-empty="true"
										v-validate="'required'"
										data-vv-name="customer_type"
									>
										
									</customer-type>
								</div>
							</div>
							<div class="col-md-7">
								<div class="form-group" :class="errors.has('customer') ? 'has-error': ''">
									<label>
										<template v-if="newStockPoint.customer_type">
											<template v-if="newStockPoint.customer_type.code == 'DISTRIBUTO'">Distributor</template>
											<template v-if="newStockPoint.customer_type.code == 'DEALER'">Dealer</template>
											<template v-if="newStockPoint.customer_type.code == 'SUPER_STOCKIST'">Super Stockist</template>
										</template>
										<template v-else>
											Customer
										</template>
									</label>
									<customer-select 
										v-model="newStockPoint.customer" 
										:company="company" 
										:customer_type="newStockPoint.customer_type" 
										:disabled="!newStockPoint.customer_type"
										v-validate="'required'"
										data-vv-name="customer"
										:only-company="true"
									></customer-select >
								</div>
							</div>
							<div class="col-md-2">
								<label style="display: block;">&nbsp;</label>
								<button class="btn btn-block btn-default" @click="add">ADD</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="box box-widget">
			<div class="box-body">
				<div class="row" style="margin-bottom: 10px;">
					<div class="col-md-3">
						<label>Customer Type</label>
						<customer-type v-model="customer_type" :allow-empty="true"></customer-type>
					</div>
					<div class="col-md-7">
						<label>
							<template v-if="customer_type">
								<template v-if="customer_type.code == 'DISTRIBUTO'">Distributor</template>
								<template v-if="customer_type.code == 'DEALER'">Dealer</template>
								<template v-if="customer_type.code == 'SUPER_STOCKIST'">Super Stockist</template>
							</template>
							<template v-else>
								Customer
							</template>
						</label>
						<customer-select 
							v-model="selectedCustomer" 
							:company="company" 
							:customer_type="customer_type" 
							:disabled="!customer_type"
							:only-company="true"
							></customer-select >
					</div>
					<div class="col-md-2">
						<label style="display: block;">&nbsp;</label>
						<button class="btn btn-block btn-default" @click="search">Filter</button>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12 table-responsive">
						<vuetable 
							:fields="tableFields"
							:api-url="apiUrl"
							pagination-path="pagination"
							data-path="customers"
							ref="vuetable"
							:css="css.table"
							:per-page="perPage"
							:append-params="appendParams"
							@vuetable:pagination-data="onPaginationData"
							@vuetable:loading="loading=true"
							@vuetable:loaded="loading=false"
							>
							<template slot="actions" slot-scope="props" >
				        		<div class="custom-actions">
				        			<button class="btn btn-xs btn-danger" @click="deleteCustomer(props.rowData)" v-if="abilities.deleteStockPointCustomers"><i class="fa fa-trash"></i></button>
				        		</div>
				      		</template>
						</vuetable>
					</div>
				</div>
				<div class="overlay" v-if="loading">
                    <i class="fa fa-sync fa-spin"></i>
                </div>
			</div>
			<div class="box-footer">
				<div style="text-align:right;">
					<vuetable-pagination ref="pagination"
		        		:css="css.pagination"
		        		@vuetable-pagination:change-page="onChangePage"
		      		></vuetable-pagination>
				</div>
			</div>
		</div>
	</div>
</template>
<script>
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import ListColumns from './CustomerListFields.js'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import {EventBus} from '../../configs/EventBus'
	import AppMixins from '../../vuex-mixin'
	import Vue from 'vue'
	import CustomerType from '../master/CustomerType'
	import CustomerSelect from '../master/CustomerSelect'
	export default{
		mixins:[AppMixins],
		components:{
			Vuetable,
			VuetablePagination,
			CustomerType,
			CustomerSelect,
		},
		props:{
			company:{
				type:Object,
				required:true,
			}
		},
		mounted(){
			/*if(!this.abilities.deleteCustomerStockPoint)
			{
				this.$refs.vuetable.hideField(3)
			}*/
        },
		computed:{
			apiUrl(){
				return `companies/${this.company.id}/stockpoints/${this.$route.params.id}/customers`
			},
            appendParams(){
				let params = {}
				if(this.customer_type)
					params.customer_type = this.customer_type.code
				if(this.selectedCustomer)
					params.customer_id = this.selectedCustomer.id
				return params
			}
        },
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:ListColumns,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				customer_type:null,
				selectedCustomer:null,
				newStockPoint:{
					customer_type:null,
					customer:null,
				},
				loading:false,
			}
		},
		methods:{
			add(){
				this.$validator.validateAll()
				.then(v => {
					if(v)
					{
						let customer_type = 'distributors'
						if(this.newStockPoint.customer_type.code == 'DEALER')
							customer_type = 'dealers'
						if(this.newStockPoint.customer_type.code == 'SUPER_STOCKIST')
							customer_type = 'super_stockists'
						EventBus.$emit("loader:show")
						Vue.axios.post(`companies/${this.company.id}/stockpoints/${this.$route.params.id}/${customer_type}`,{customer:this.newStockPoint.customer.id})
						.then(response => {
							swal({
								title: "Success",
								text: "Customer added",
								icon: "success"
							})
							.then(v =>{
								this.$refs.vuetable.reload()
								this.newStockPoint.customer = null
							})
						})
						.catch(error => {
							swal({
								title: "Error",
								text: "Customer already present",
								icon: "error"
							})
							.then(v => {
								this.newStockPoint.customer = null
							})
						})
					}
				})
			},
			search(){
				this.$refs.vuetable.reload()
			},
			deleteCustomer(row){
				console.log(row)
				let self = this
				let customer_type = null
				if(row.type == "Dealer")
					customer_type ='dealers'
				if(row.type == 'Distributor')
					customer_type = 'distributors'
				if(row.type == 'Super Stockist')
					customer_type = 'super_stockists'

				let url = `companies/${this.company.id}/stockpoints/${this.$route.params.id}/${customer_type}/${row.id}`
				swal({
                    title: 'Remove customer?',
                    text: `Are you you want to remove customer ${row.name} from stockpoint?`,
                    icon:'warning',
                    buttons: {
                        cancel: true,
                        confirm: true,
                    },
                })
                .then(v => {
                	if(v){
                		EventBus.$emit('loader:show')
                		Vue.axios.delete(url)
                		.then(response => {
                			EventBus.$emit('loader:hide')
                			self.$refs.vuetable.reload()
                		})
                		.catch(error => {

                		})
                	}
                })
			},
			onPaginationData (tablePagination) {
		      this.$refs.pagination.setPaginationData(tablePagination)
		    },
		    transform: function(data){
		    	var transformed = {}
		    	transformed.pagination = {

		    		total:data.total,
		    		per_page :this.perPage,
		    		current_page: data.page,
		    		last_page: Math.ceil(data.total / this.perPage),
		    		from: ((data.page-1) * this.perPage) +1,
		    		to:((data.page-1) * this.perPage) + data.customers.length
		    	}
		    	transformed.customers = data.customers
		    	return transformed
		    },
		    
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
		    },
		    search(){
		    	let self = this
		    	Vue.nextTick(function(){
		    		self.$refs.vuetable.reload()	
		    	})
        	},
        	clear(){
        		this.customer_name = ""
        		this.customer_id = ""
        		this.search()
        	},
		}
	}
</script>
<style>
	.has-actions{
		width: 1%;
		white-space: nowrap;
	}
	.vuetable-th-id{
		width: 1%;
		white-space: nowrap;	
	}
	.overlay{
		position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.7);
    z-index: 1000;
	}
</style>