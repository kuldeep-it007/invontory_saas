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
								<input type="text" class="form-control" v-model="customer_id">
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label>Customer Name</label>
								<input type="text" class="form-control" v-model="customer_name">
							</div>
						</div>
						<div class="col-md-1">
							<div class="form-group">
								<label style="display: block;">&nbsp;</label>
								<button class="btn btn-default" @click="search">Search</button>
							</div>
						</div>
						<div class="col-md-1">
							<div class="form-group">
								<label style="display: block;">&nbsp;</label>
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
						<div class="col-md-10" v-if="!this.$route.params.distributorid"><!--//#826-->
			        	    <div class="form-group" :class="errors.has('selectedCustomers') ? 'has-error' : ''">
			                    <label>Distributor</label>
			                    <distributor-multi-select v-model="selectedCustomers" :company="company" :customer_type="customer_type" :disabled="!company" :required="true" v-validate="'required'" data-vv-name="selectedCustomers" :multiple="true"></distributor-multi-select>
			                </div>
			            </div>
			            <div class="col-md-2 text-right pull-right">
							<div class="form-group">
								<label style="display: block;">&nbsp;</label>
								<a href="#" @click.prevent="exportDealers"><i class="fas fa-download"></i> Export Dealers</a>
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
						<vuetable 
							:fields="tableFields"
							:api-url="apiUrl"
							:append-params="appendParams"
							pagination-path="pagination"
							data-path="dealers"
							ref="vuetable"
							:css="css.table"
							:per-page="perPage"
							@vuetable:pagination-data="onPaginationData">
							<template v-if= "!isCustomer" slot="distributor_name" slot-scope="props" >
				      			<template v-if="props.rowData.distributor_name">
				      				{{props.rowData.distributor_name}} ({{props.rowData.distributor_nav_id}})
				      			</template>
				      		</template>
							<template slot="actions" slot-scope="props" >
				        		<div class="custom-actions">
				        			<router-link class="btn btn-xs btn-info" :to="{name:'companies.dealers.edit',params:{id:$route.params.id,dealerid:props.rowData.id}}" v-if="companyDealers"><i class="fa fa-pencil-alt"></i></router-link>
				        			<router-link class="btn btn-xs btn-info" :to="{name:'companies.distributors.dealers.edit',params:{id:$route.params.id,distributorid:props.rowData.distributor_id, dealerid:props.rowData.id}}" v-if="distributorDealers"><i class="fa fa-pencil-alt"></i></router-link>
				        			<router-link class="btn btn-xs btn-info" :to="{name:'companies.dealers.product_attributes',params:{id:$route.params.id,dealerid:props.rowData.id}}" v-if="companyDealers"><i class="fa fa-parking"></i></router-link>
				        			<router-link class="btn btn-xs btn-info" :to="{name:'companies.distributors.dealers.product_attributes',params:{id:$route.params.id,distributorid:props.rowData.distributor_id,dealerid:props.rowData.id}}" v-if="distributorDealers"><i class="fa fa-parking"></i></router-link>
				        			<router-link class="btn btn-xs btn-info" :to="{name:'companies.dealers.users',params:{id:$route.params.id,dealerid:props.rowData.id}}" v-if="companyDealers"><i class="fa fa-user-friends"></i></router-link>
				        			<router-link class="btn btn-xs btn-info" :to="{name:'companies.distributors.dealers.users',params:{id:$route.params.id,distributorid:props.rowData.distributor_id,dealerid:props.rowData.id}}" v-if="distributorDealers"><i class="fa fa-user-friends"></i></router-link>
				        			<button class="btn btn-xs btn-success" @click="setPaymentType(props.rowData, props.rowData.id)" :disabled="props.rowData.distributor_id"><i class="fa fa-money-check"></i></button>
				        		</div>
				      		</template>
						</vuetable>
						<payment-type-modal
					        ref="PaymentTypeModal" 
					        @payment-type-set="OnPaymentTypeSet">
					    </payment-type-modal>
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
	import DistributorMultiSelect from '../master/DistributorMultiSelect'
	export default{
		mixins:[AppMixins],
		components:{
			Vuetable,
			VuetablePagination,
			RegionSelect,
			Company,
			PaymentTypeModal,
			DistributorMultiSelect,
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
			if(this.$route.params.distributorid)
				this.$refs.vuetable.hideField(4)
        },
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:DistributorListColumns,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				companies:[],
				customer_name:"",
				customer_id:"",
				region:null,
				company:null,
				selectedDealerId:"",
				selectedDealer:null,
				distributor_id:null,
				selectedCustomers:[],
				customer_type:{code:'DISTRIBUTO',name:"Distributor"},
			}
		},
		computed:{
			companyDealers(){
				return !this.$route.params.distributorid
			},
			distributorDealers(){
				return !(!this.$route.params.distributorid)
			},
			apiUrl(){
				if(this.$route.params.distributorid)
				{
					return `companies/${this.$route.params.id}/distributors/${this.$route.params.distributorid}/dealers`
				}	
				return `companies/${this.$route.params.id}/dealers`
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
		    		to:((data.page-1) * this.perPage) + data.dealers.length
		    	}
		    	transformed.dealers = data.dealers
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
        	setPaymentType(dealer){
            	this.selectedDealer = dealer
            	this.selectedDealerId = dealer.id
                this.$refs.PaymentTypeModal.show(dealer)           
            },
            OnPaymentTypeSet(dealer)
            {
            	let d = _.findWhere(this.$refs.vuetable.tableData,{id:dealer.id})
            	d.paymentTypes = Object.assign({},dealer.paymentTypes)
            },
            exportDealers(){
            	let url = ''
            	if(this.$route.params.distributorid)
				{
					url = `companies/${this.$route.params.id}/distributors/${this.$route.params.distributorid}/export_dealers`
				}	
				else
					url = `companies/${this.$route.params.id}/export_dealers`

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