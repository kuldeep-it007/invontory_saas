<template>
	<div v-if="abilities.manageCoDistributors">
		<section class="content-header">
			<h1>Co Distributors</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label>Distributor Name</label>
										<input type="text" class="form-control" v-model="customer_name" @keyup.enter="search">
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label>Distributor ID</label>
										<input type="text" class="form-control" v-model="customer_id" @keyup.enter="search">
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
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<div class="radio">
	  										<label>
	    										<input type="radio" v-model="type" value="1">
	    										Distributors to whom I place orders with
	  										</label>
										</div>
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
										<div class="radio">
	  										<label>
	    										<input type="radio" v-model="type" value="2">
	    										Distributors who place orders to me
	  										</label>
										</div>
									</div>
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
	import appMixin from '../../vuex-mixin.js'
	import coDistributorFields from './coDistributorFields.js'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import {EventBus} from '../../configs/EventBus'
	import EditModal from './EditCoDistributorModal'
	import Vue from 'vue'
	export default{
		mixins:[appMixin],
		components:{
			Vuetable,
			VuetablePagination,
			EditModal,
		},
		data(){
			return {
				css:vuetableCssConfig,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				tableFields:coDistributorFields,
				customer_name:"",
				customer_id:"",
				type:1,
			}
		},
		computed:{
			apiUrl(){
				let url = `companies/${this.distributor.company.id}/distributors/${this.distributor.id}/co_distributors?type=${this.type}`
				return url
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
            	if(this.selectedDealer)
					filters.customer_id = this.selectedDealer.nav_id
            	return filters
            },
            effectiveCompany(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor.company
                }
            },
            effectiveDisributor(){
                if(this.isCustomer)
                {
                    if(this.distributor)
                        return this.distributor
                }
                return null
            },
		},
		mounted(){
			this.$refs.vuetable.hideField(2)
			this.$refs.vuetable.hideField(3)
			this.$refs.vuetable.hideField(4)
		},
		methods:{
			coDistributorUpdated(){
				this.$refs.vuetable.reload()
			},
			edit(dist){
				this.$refs.editModal.show(dist)
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
        		this.selectedDealer = null
        		this.search()
        	},
		},
		watch:{
			type(){
				if(this.type == 1)
				{
					this.$refs.vuetable.hideField(2)
					this.$refs.vuetable.hideField(3)
					this.$refs.vuetable.hideField(4)
				}
				else
				{
					this.$refs.vuetable.showField(2)
					this.$refs.vuetable.showField(3)	
					this.$refs.vuetable.showField(4)
				}
			}
		}
	}
</script>