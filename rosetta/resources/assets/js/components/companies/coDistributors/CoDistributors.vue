<template>
	<div v-if="abilities.manageCoDistributors">
		<section class="content-header">
			<h1>Manage Co Distributors <span class="pull-right"><button class="btn btn-default" v-if="selectedDistributor" @click="showAdd">ADD</button></span></h1>
		</section>
		<section class="content">
			<div class="box box-widget">
				<div class="box-body">
					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label>Distributor</label>
								<distributor-select
									:customer_type="customerType"
									:company="effectiveCompany"
									v-model="selectedDistributor"
									ref="distributorSelect"
								>
									
								</distributor-select>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12">
							<p>Select distributor above to see list of co-distributors from whom selected distributor can order items</p>
						</div>
					</div>
				</div>
			</div>
			<div class="box box-widget" v-if="enableAdd">
				<div class="box-header">
					<h3 class="box-title">Add Co-Distributor</h3>
				</div>
				<div class="box-body">
					<p>Select distributor below with whom <b>{{selectedDistributor.name}}</b> can place orders with</p>
					<div class="row">
						<div class="col-md-3">
							<div class="form-group" :class="errors.has('co_dostributor') ? 'has-error' : ''">
								<label>Distributor</label>
								<distributor-select
									:customer_type="customerType"
									:company="effectiveCompany"
									v-model="selectedCoDistributor"
									ref="coDistributorSelect"
									v-validate="'required'"
									data-vv-name="co_dostributor"
								>
								</distributor-select>
							</div>
						</div>
						<div class="col-md-3">
							<div class="form-group" :class="errors.has('credit_limit') ? 'has-error' : ''">
								<label>Credit Limit</label>
								<input class="form-control" v-model="credit_limit" v-validate="'decimal'" data-vv-name="credit_limit">
							</div>
						</div>
						<div class="col-md-3">
							<div class="form-group" :class="errors.has('overdue_balance') ? 'has-error' : ''">
								<label>Overdue Balance</label>
								<input class="form-control" v-model="overdue_balance" v-validate="'decimal'" data-vv-name="overdue_balance">
							</div>
						</div>
						<div class="col-md-3">
							<div class="form-group" :class="errors.has('payment_terms') ? 'has-error' : ''">
								<label>Payment Terms</label>
								<payment-terms v-model="payment_terms" v-validate="'required'" data-vv-name="payment_terms">
								</payment-terms>
							</div>
						</div>
					</div>
				</div>
				<div class="box-footer text-right">
					<button class="btn btn-sm btn-success" @click="addCoDistributor">ADD</button>
					<button class="btn btn-sm btn-default" @click="closeAdd">cancel</button>
				</div>
			</div>
			<div class="box box-widget" v-if="selectedDistributor">
				<div class="box-body">
					<vuetable 
						:fields="tableFields"
						:api-url="apiUrl"
						pagination-path="pagination"
						data-path="distributors"
						ref="vuetable"
						:css="css.table"
						:per-page="perPage"
						@vuetable:pagination-data="onPaginationData"
						@vuetable:loading="loadingData"
						@vuetable:loaded="dataLoaded"
						>
						<template slot="actions" slot-scope="props" >
	        				<div class="custom-actions">
	        					<a href="#" @click.prevent="edit(props.rowData)"><i class="fa fa-pencil-alt"></i></a>
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
			<edit-modal
				ref="editModal"
				@co-distributor:updated="coDistributorUpdated"
			></edit-modal>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import Vue from 'vue'
	import appMixin from '../../../vuex-mixin.js'
	import DistributorSelect from '../../master/CustomerSelect'
	import coDistributorFields from './coDistributorFields.js'
	import Vuetable from '../../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../../vendor/vuetable2/VuetablePagination'
	import vuetableCssConfig from '../../../configs/VuetableCssConfig'
	import {EventBus} from '../../../configs/EventBus'
	import PaymentTerms from '../../master/PaymentTerms'
	import EditModal from './EditCoDistributorModal'
	export default{
		mixins : [appMixin],
		components:{
			DistributorSelect,
			Vuetable,
			VuetablePagination,
			PaymentTerms,
			EditModal,
		},
		computed:{
			effectiveCompany(){
				if(this.$route.params.id)
					return {id:this.$route.params.id}
			},
			apiUrl(){
				return `companies/${this.effectiveCompany.id}/distributors/${this.selectedDistributor.id}/co_distributors?type=1`
			}
		},
		mounted(){
			this.$refs.distributorSelect.loadCustomers()
		},
		data(){
			return {
				selectedDistributor:null,
				selectedCoDistributor:null,
				customerType:{
					code:"DISTRIBUTO",
					name:"Distributor"
				},
				css:vuetableCssConfig,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				tableFields:coDistributorFields,
				enableAdd:false,
				credit_limit:0,
				overdue_balance:0,
				payment_terms:null
			}
		},
		methods:{
			coDistributorUpdated(){
				this.$refs.vuetable.reload()
			},
			edit(dist){
				this.$refs.editModal.show(this.effectiveCompany,this.selectedDistributor,dist)
			},
			closeAdd(){
				this.credit_limit = 0
				this.overdue_balance = 0
				this.selectedCoDistributor = null
				this.enableAdd = false
			},
			addCoDistributor(){
				this.$validator.validateAll()
				.then(v => {
					if(v)
					{
						if(this.selectedDistributor.id == this.selectedCoDistributor.id)
						{
							swal({
		                        title:"Error",
		                        text: 'you can not add same distributor as co distributor',
		                        icon: 'error'
		                    })
		                    return
						}
						let input = {
							credit_limit : this.credit_limit,
							overdue_balance : this.overdue_balance,
							payment_terms : this.payment_terms.value,
							distributor_id: this.selectedCoDistributor.id
						}
						EventBus.$emit('loader:show')
						Vue.axios.post(`companies/${this.effectiveCompany.id}/distributors/${this.selectedDistributor.id}/co_distributors`,input)
						.then(response => {
							this.closeAdd()
							this.$refs.vuetable.reload()
						})
						.catch(error => {
							swal({
		                        title:"Error",
		                        text: 'Co distributor already present',
		                        icon: 'error'
		                    })
						})

					}
				})
			},
			showAdd(){
				let self = this
				this.enableAdd = true
				Vue.nextTick(function(){
					self.$refs.coDistributorSelect.loadCustomers()
				})
			},
			dataLoaded(){
				EventBus.$emit('loader:hide')
			},
			loadingData(){
				EventBus.$emit('loader:show')
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
		    		to:((data.page-1) * this.perPage) + data.distributors.length
		    	}
		    	transformed.distributors = data.distributors
		    	return transformed
		    },
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
		    }
		}
	}
</script>