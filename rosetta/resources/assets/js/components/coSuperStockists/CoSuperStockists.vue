<template>
	<div v-if="abilities.manageCoSuperStockists">
		<section class="content-header">
			<h1>Co Super Stockists</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label>Super Stockist Name</label>
										<input type="text" class="form-control" v-model="super_stockist_name" @keyup.enter="search">
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label>Super Stockist ID</label>
										<input type="text" class="form-control" v-model="super_stockist_id" @keyup.enter="search">
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
	    										Super Stockists to whom I place orders with
	  										</label>
										</div>
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
										<div class="radio">
	  										<label>
	    										<input type="radio" v-model="type" value="2">
	    										Super Stockists who place orders to me
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
								data-path="super_stockists"
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
				@co-super-stockist:updated="coSuperstockistUpdated"
			></edit-modal>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import coSuperStockistFields from './coSuperStockistFields.js'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import {EventBus} from '../../configs/EventBus'
	import EditModal from './EditCoSuperStockistModal'
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
				tableFields:coSuperStockistFields,
				super_stockist_name:"",
				super_stockist_id:"",
				type:1,
			}
		},
		computed:{
			apiUrl(){
				let url = `companies/${this.superStockist.company.id}/super_stockists/${this.superStockist.id}/co_super_stockists?type=${this.type}`
				return url
			},
			appendParams(){
            	let filters = {}
            	if(this.super_stockist_name)
            	{
            		filters.super_stockist_name = this.super_stockist_name
            	}
            	if(this.super_stockist_id)
            	{
            		filters.super_stockist_id = this.super_stockist_id
            	}
            	if(this.selectedDealer)
					filters.super_stockist_id = this.selectedDealer.nav_id
            	return filters
            },
            effectiveCompany(){
                if(this.isCustomer)
                {
                    if(this.superStockist)
                        return this.superStockist.company
                }
            },
            effectiveSuperStockist(){
                if(this.isCustomer)
                {
                    if(this.superStockist)
                        return this.superStockist
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
			coSuperStockistUpdated(){
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
		    		to:((data.page-1) * this.perPage) + data.super_stockists.length
		    	}
		    	transformed.super_stockists = data.super_stockists
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
        		this.super_stockist_name = ""
        		this.super_stockist_id = ""
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