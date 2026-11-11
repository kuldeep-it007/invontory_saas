<template>
	<div>
		<div class="row">
			<div class="col-md-12">
				<div class="box box-widget">
					<div class="box-body">
						<!--	<div class="row" v-if="!isCustomer && this.state.user.company == null">
							<div class="col-md-3">
								<div class="form-group">
									<label>Region</label>
									<region-select v-model="region" :allow-empty="true"></region-select>
								</div>
							</div>
							<div class="col-md-3">
								<div class="form-group">
									<label>Company</label>
									<company v-model="company" :region="region" :disabled="!region"></company>
								</div>
							</div>
						</div>-->
						<div class="col-md-4">
							<div class="form-group">
								<label>Customer Id</label>
								<!--<input type="text" class="form-control" v-model="customer_id" :disabled="!company">-->
								<input type="text" class="form-control" v-model="customer_id">
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label>Customer Name</label>
								<!--<input type="text" class="form-control" v-model="customer_name" :disabled="!company">-->
								<input type="text" class="form-control" v-model="customer_name">
							</div>
						</div>
						<div class="col-md-1">
							<div class="form-group">
								<label style="display: block;">&nbsp;</label>
								<!--<button class="btn btn-default" :disabled="!company" @click="search">Search</button>-->
								<button class="btn btn-default" @click="search">Search</button>
							</div>
						</div>
						<div class="col-md-1">
							<div class="form-group">
								<label style="display: block;">&nbsp;</label>
								<!--<button class="btn btn-default" :disabled="!company" @click="clear">Clear</button>-->
								<button class="btn btn-default" @click="clear">Clear</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<div class="box box-widget">
					<div class="box-body">
						<div class="col-md-10" v-if="!this.$route.params.super_stockist_id"><!--//#894-->
							<div class="form-group" :class="errors.has('selectedCustomers') ? 'has-error' : ''">
								<label>Super Stockist</label>
								<super-stockist-multi-select v-model="selectedCustomers" :company="company"
									:customer_type="customer_type" :disabled="!company" :required="true"
									v-validate="'required'" data-vv-name="selectedCustomers"
									:multiple="true"></super-stockist-multi-select>
							</div>
						</div>
						<div class="col-md-2 text-right pull-right">
							<div class="form-group">
								<label style="display: block;">&nbsp;</label>
								<a href="#" @click.prevent="exportDistributors"><i class="fas fa-download"></i> Export
									Distributors</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<div class="box box-widget">
					<div class="box-body">
						<vuetable :fields="tableFields" :api-url="apiUrl" :append-params="appendParams"
							pagination-path="pagination" data-path="distributors" ref="vuetable" :css="css.table"
							:per-page="perPage" @vuetable:pagination-data="onPaginationData">

							<template v-if="!isCustomer && props.rowData.display_name" slot="display_name"
								slot-scope="props">
								{{ props.rowData.display_name }}
							</template>
							<template v-if="!isCustomer && props.rowData.super_stockist_name" slot="super_stockist_name" slot-scope="props">
									{{props.rowData.super_stockist_name}} ({{props.rowData.super_stockist_nav_id}})
							</template>							
							<template slot="actions" slot-scope="props">
								<div class="custom-actions">
									<router-link class="btn btn-xs btn-info"
										:to="{name:'companies.distributors.addresses',params:{id:$route.params.id,distributorid:props.rowData.id}}"
										title="manage addresses">
										<i class="fa fa-globe"></i>
									</router-link>
									<router-link class="btn btn-xs btn-warning"
										:to="{name:'companies.distributors.dealers',params:{id:$route.params.id,distributorid:props.rowData.id}}"
										title="view dealers">
										<i class="fa fa-users"></i>
									</router-link>
									<router-link class="btn btn-xs btn-info"
										:to="{name:'companies.distributors.edit',params:{id:$route.params.id,distributorid:props.rowData.id}}"
										v-if="companyDistributors"><i class="fa fa-pencil-alt"></i></router-link>
									<router-link class="btn btn-xs btn-info"
										:to="{name:'companies.super_stockists.distributors.edit',params:{id:$route.params.id,super_stockist_id:props.rowData.super_stockist_id, distributorid:props.rowData.id}}"
										v-if="superStockistDistributors"><i class="fa fa-pencil-alt"></i></router-link>

									<router-link class="btn btn-xs btn-info"
										:to="{name:'companies.distributors.product_attributes',params:{id:$route.params.id,distributorid:props.rowData.id}}"><i
											class="fa fa-parking"></i></router-link>
									<router-link class="btn btn-xs btn-info"
										:to="{name:'companies.distributors.users',params:{id:$route.params.id,distributorid:props.rowData.id}}"><i
											class="fa fa-user-friends"></i></router-link>
									<router-link class="btn btn-xs btn-success"
										:to="{name:'companies.distributors.stock',params:{id:$route.params.id,distributorid:props.rowData.id}}"><i
											class="fa fa-box-open"></i></router-link>
									<button class="btn btn-xs btn-success"
										@click="setPaymentType(props.rowData, props.rowData.id)"><i
											class="fa fa-money-check"></i></button>
									<button class="btn btn-xs btn-danger"
										@click="SetPermissions(props.rowData, props.rowData.id)"><i
											class="fa fa-user-lock"></i></button>
								</div>

							</template>
						</vuetable>
						<payment-type-modal ref="PaymentTypeModal" @payment-type-set="OnPaymentTypeSet">
						</payment-type-modal>
						<set-permissions-modal ref="SetPermissionsModal" @permissions-set="OnPermissionsSet">
						</set-permissions-modal>
					</div>
					<div class="box-footer">
						<div style="text-align:right;">
							<vuetable-pagination ref="pagination" :css="css.pagination"
								@vuetable-pagination:change-page="onChangePage"></vuetable-pagination>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
<script>
	import RegionSelect from '../region/Select'
	import Company from '../companies/Select'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import DistributorListColumns from './ListFields.js'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import {EventBus} from '../../configs/EventBus'
	import AppMixins from '../../vuex-mixin'
	import Vue from 'vue'
	import PaymentTypeModal from './PaymentTypeModal'
	import { saveAs } from 'file-saver'
	import SetPermissionsModal from './SetPermissionsModal'
	import SuperStockistMultiSelect from '../master/SuperStockistMultiSelect'
	export default{
		mixins:[AppMixins],
		components:{
			Vuetable,
			VuetablePagination,
			RegionSelect,
			Company,
			PaymentTypeModal,
			SetPermissionsModal,
			SuperStockistMultiSelect,
		},
		mounted(){
        	let self = this
			if((!self.isCustomer) && self.state.user.company)
			{
				self.company = 	Object.assign({},self.state.user.company)
			}
			else
			{
				self.company = Object.assign({}, {
					id:self.$route.params.id
				})
			}
			if(this.$route.params.super_stockist_id)
				this.$refs.vuetable.hideField(4)
        },
		computed:{
            companyDistributors(){
				return !this.$route.params.super_stockist_id
			},
			superStockistDistributors(){
				return !(!this.$route.params.super_stockist_id)
			},
			apiUrl(){
				if(this.$route.params.super_stockist_id)
				{
					return `companies/${this.$route.params.id}/super_stockists/${this.$route.params.super_stockist_id}/distributors`
				}	
				return `companies/${this.$route.params.id}/distributors`
			},
            appendParams(){
            	let filters = {}
            	if(this.customer_name)
            	{
            		filters.customer_name = this.customer_name
            	}
            	if(this.customer_id)
            	{
            		filters.customer_id = this.customer_id
            	}
            	return filters
            }
        },
		data(){
			return {
				customer_name:"",
				customer_id:"",
				region:null,
				company:null,
				css:vuetableCssConfig,
				tableFields:DistributorListColumns,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				companies:[],
			//	apiUrl:`companies/${this.$route.params.id}/distributors`,
				selectedDistributorId:"",
				selectedDistributor:null,
				super_stockist_id:null,
				selectedCustomers:[],
				customer_type:{code:'SUPER_STOCKIST',name:"SuperStockist"},
			}
		},
		methods:{
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
		    		to:((data.page-1) * this.perPage) + data.distributors.length
		    	}
		    	transformed.distributors = data.distributors
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
            setPaymentType(distributor){
            	this.selectedDistributor = distributor
            	this.selectedDistributorId = distributor.id
                this.$refs.PaymentTypeModal.show(distributor)           
            },
            SetPermissions(distributor)
            {
            	this.selectedDistributor = distributor
            	this.selectedDistributorId = distributor.id
                this.$refs.SetPermissionsModal.show(distributor)    
            },
            OnPermissionsSet(distributor)
            {
            	let d = _.findWhere(this.$refs.vuetable.tableData,{id:distributor.id})
            	d.abilities = distributor.abilities
            },
            OnPaymentTypeSet(distributor)
            {
            	let d = _.findWhere(this.$refs.vuetable.tableData,{id:distributor.id})
            	d.paymentTypes = Object.assign({},distributor.paymentTypes)
            },
            exportDistributors(){
            	let url = ''
            	if(this.$route.params.super_stockist_id)
				{
					url = `companies/${this.$route.params.id}/super_stockists/${this.$route.params.super_stockist_id}/export_distributors`
				}	
				else
					url = `companies/${this.$route.params.id}/export_distributors`

				let params = {selectedCustomers : this.selectedCustomers}//#826
            	EventBus.$emit('loader:show')
            	Vue.axios.post(url,params,{responseType:'blob'})
                .then(response => {
                    EventBus.$emit('loader:hide')
                    let disposition = response.headers['content-disposition']
                    console.log(disposition,disposition.match(/filename=(.*)/)[1])
					let filename = decodeURI(disposition.match(/filename=(.*)/)[1])
				//	let filename = decodeURI(disposition.match(/filename="(.*)"/)[1])
					saveAs(response.data, filename);
                })
                .catch(error => {
                	console.log(error)
                })
            },//#813
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
</style>