<template>
	<div v-if="abilities.companies || abilities.viewDealers">
		<section class="content-header">
			<h1>Dealers - Pending Approval 
			</h1>
		</section>
		<section class="content">
			<div class="row" v-if="!isCustomer">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row" v-if="this.state.user.company == null">
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
							</div>
							<div class="row" v-if="!isCustomer">
								<div class="col-md-4">
				                    <div class="form-group">
				                        <label>Distributor</label>
				                        <customer-select v-model="selectedCustomer" :customer_type="customer_type" :company="company" :disabled="!company"></customer-select>
				                    </div>
				                </div>
								<div class="col-md-4">
									<div class="form-group">
										<label>Dealer</label>
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
			</div>
			<div class="row" v-if="company">
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
								track-by="id"
								:css="css.table"
								:per-page="perPage"
								@vuetable:pagination-data="onPaginationData">
								<template slot="actions" slot-scope="props" >
					        		<div class="custom-actions">
					        			<button class="btn btn-sm btn-success" @click="approve(props.rowData, props.rowIndex)" v-if="abilities.approveDealers">APPROVE</button>
					        			<button class="btn btn-sm btn-warning" @click="rejectDealer(props.rowData,props.rowIndex)" v-if="abilities.rejectDealers">REJECT</button>
					        		</div>
					      		</template>
							</vuetable>
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
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import MyDealerListFields from './PendingDealersFields.js'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import {EventBus} from '../../configs/EventBus'
	import CustomerSelect from '../master/CustomerSelect'
	import CustomerType from '../master/CustomerType'
	import Vue from 'vue'
	import RegionSelect from '../region/Select'
	import Company from '../companies/Select'
	export default{
		mixins:[appMixin],
		components:{
			Vuetable,
			VuetablePagination,
			CustomerSelect,
			CustomerType,
			RegionSelect,
			Company,
		},
		mounted(){
			if(this.isCustomer)
			{
				Vue.axios.get(`companies/${this.distributor.company.id}`)
				.then(response => {
					this.company = Object.assign({},response.data)
				})
			}
			if(this.state.user.company)
			{
				Vue.axios.get(`companies/${this.state.user.company.id}`)
				.then(response => {
					this.company = Object.assign({},response.data)
				})	
			}
			if(this.isCustomer)
			{
				let self = this
				Vue.nextTick(function(){
					self.$refs.vuetable.hideField(2)
					self.$refs.vuetable.hideField(4)
				})
			}
        },
		data(){
			return {
				css:vuetableCssConfig,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				tableFields:MyDealerListFields,
				customer_name:"",
				customer_id:"",	
				company:null,
				customer_type:{code:'DISTRIBUTO',name:"Distributor"},
				selectedCustomer:null,
				region:null,
				company:null
			}
		},
		computed:{
			apiUrl(){
				let self = this
				if(self.distributor)
					return `companies/${self.distributor.company.id}/distributors/${self.distributor.id}/temp_dealers`
				if(self.company)
					return `companies/${self.company.id}/temp_dealers`

			},
			appendParams(){
            	let filters = {}
            	if(this.customer_name)
            	{
            		filters.customer_name = this.customer_name
            	}
            	if(this.selectedCustomer)
            	{
            		filters.customer_id = this.selectedCustomer.id
            	}
            	return filters
            },
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
        		this.selectedCustomer = null
        		this.search()
        	},
        	approve(dealer, index)
        	{
				let self = this
              	EventBus.$emit('loader:show', 'Please wait, this may take a minute...')
                Vue.axios.put(`companies/${this.company.id}/temp_dealers/${dealer.id}`)
                .then(response => {
                    swal({
                        title:"Dealer Approved!",
                        icon:"success"
                    })
                    self.$refs.vuetable.tableData.splice(index,1)
                })
                .catch(error => {
	            })
        	},
        	rejectDealer(dealer, index)
			{
				let self = this
				let textArea = document.createElement('textarea')
				textArea.onkeyup = function () {
					swal.setActionValue({
							confirm: this.value
					});
					};
				textArea.style = 'width:90%;height:140px;'
				swal({
					text:"Rejection Reason",
					content:textArea,
					buttons: {
			            cancel: {
			            	text: 'cancel',
			            	visible: true,
			            	closeModal: true,
			            },
			            confirm: {
			            	text: 'Reject',
			            	visible: true,
			            	closeModal: false,

			            },
			        }
				})
				.then(reason => {
					if(reason !== null)
					{
						if(reason !== '' && reason !== true)
						{
							
							let url = `companies/${this.company.id}/reject_temp_dealers/${dealer.id}`
							let input = {
								reason:reason
							}
			                Vue.axios.put(url, input)
			                .then(response => {
			                    swal({
			                        title:"Dealer Rejected!",
			                        icon:"success"
			                    })
			                    self.$refs.vuetable.tableData.splice(index,1)
			                })
			                .catch(error => {
			                })
						}
						else
						{
							swal('Please provide reason')
						}
					}
				})					
			}
		},
	}
</script>