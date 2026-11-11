<template>
	<div v-if="abilities.manageReturns">
		<section class="content-header">
			<h1>Return Items</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label>Dealer</label>
										<dealer-select v-model="selectedDealer" :selected-distributor= "effectiveDisributor" :company="effectiveCompany" v-if="effectiveCompany"></dealer-select>
									</div>
								</div>
								<div class="col-md-2">
									<div class="form-group">
										<label>Status</label>
										<return-status v-model="status" :allow-empty="true"></return-status>
									</div>
								</div>
								<!--<div class="col-md-2">
									<div class="form-group">
										<label>SO NO</label>
										<input type="text" class="form-control" v-model="number">
									</div>
								</div>-->
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
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row">
								<div class="col-md-12 table-responsive">
									<vuetable 
										:fields="tableFields"
										:api-url="apiUrl"
										:append-params="appendParams"
										pagination-path="pagination"
										data-path="return_items"
										:per-page="perPage"
										ref="vuetable"
										:css="css.table"
										@vuetable:pagination-data="onPaginationData"
										>
							      		<template slot="category" slot-scope="props" >
							      			<template v-if="props.rowData.product.category.code">
							      				{{props.rowData.product.category.display_name}}<br><span class="product-category">{{props.rowData.product.category.code}}</span>
							      			</template>
							      		</template>
							      		<template slot="collection" slot-scope="props" >
							      			<template v-if="props.rowData.product.collection.code">
							      				{{props.rowData.product.collection.display_name}}<br><span class="product-category">{{props.rowData.product.collection.code}}</span>
							      			</template>
							      		</template>
							      		<template slot="colour" slot-scope="props" >
							      			<template v-if="props.rowData.product.colour.code">
							      				{{props.rowData.product.colour.display_name}}<br><span class="product-category">{{props.rowData.product.colour.code}}</span>
							      			</template>
							      		</template>
							      		<template slot="customer" slot-scope="props" >
							      			<template v-if="props.rowData.customer">
							      				{{props.rowData.customer.name}} ({{props.rowData.customer.nav_id}})
							      			</template>
							      		</template>
							      		<template slot="quantity" slot-scope="props" >
                                        	{{props.rowData.quantity}} <sup>{{props.rowData.product.base_unit_of_measure}}</sup> <span class="label label-default pull-right">{{props.rowData.rate_type}}</span>
                                    	</template>
                                    	<template slot="actions" slot-scope="props" >
                                        <div class="custom-actions" v-if="props.rowData.status == 'Pending'">
                                        	<button class="btn btn-success btn-xs" @click="approve(props.rowData,props.rowIndex)">APPROVE</button>
                                            <button class="btn btn-danger btn-xs" @click="reject(props.rowData,props.rowIndex)">REJECT</button>
                                        </div>
                                    </template>
									</vuetable>
								</div>
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
			</div>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import ReturnListColumns from './ListFields.js'
	import VueCountdown from '@xkeshi/vue-countdown'
	import {EventBus} from '../../configs/EventBus'
	import ReturnStatus from '../master/ReturnStatus'
    import DealerSelect from '../master/DealerSelect'
	import Vue from 'vue'
	export default{
		mixins : [appMixin],
		components:{
			VuetablePagination,
			Vuetable,
			VueCountdown,
			ReturnStatus,
            DealerSelect,
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:ReturnListColumns,
				perPage:50,
				sideBarWasOpen:false,
			//	number:"",
				status:"",
				selectedDealer:null,
				selectedItem:null,
			}
		},
		computed:{
			apiUrl(){
				return `distributors/${this.distributor.id}/return_items`
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
            appendParams(){
            	let filters = {}
            /*	if(this.number)
            	{
            		filters.number = this.number
            	}*/
            	if(this.status)
            	{
            		filters.status = this.status
            	}
            	if(this.selectedDealer)
					filters.customer_id = this.selectedDealer.id
            	return filters
            }
		},
		mounted(){
			if(this.state.user.company)
			{
				this.company = 	Object.assign({},this.state.user.company)
			}
			Vue.nextTick(function(){
                if(!$('body').hasClass('sidebar-collapse'))
                {
                    self.sideBarWasOpen = true
                    $('[data-toggle="push-menu"]').pushMenu('toggle')
                }
            })
		},
		destroyed(){
            let self = this
            if(this.sideBarWasOpen)
            {
                Vue.nextTick(function(){
                	if($('body').hasClass('sidebar-collapse'))
                	{
                    	$('[data-toggle="push-menu"]').pushMenu('toggle')
                	}
            	})
            }
        },
		methods:{
			approve(item,index){
				let self = this
				//this.selectedItem = item
				let url = `distributors/${item.customer.id}/return_items/${item.id}`
				swal({
		    		title: 'Approve Return?',
		    		icon:'warning',
  					buttons: {
    					cancel: true,
    					confirm: true,
  					},
				})
				.then(value => {
					if(value)
					{
						let input = {
		                	status : 'Approve',
		                }
						EventBus.$emit('loader:show')
		                Vue.axios.put(url, input)
		                .then(response => {
		                    EventBus.$emit('loader:hide')
		                 //   this.selectedItem.status = 'Approved'
		                    //let tableData = [...]
		                    self.$refs.vuetable.tableData.splice(index,1)
		                    swal({
		                        title:"Return Approved",
		                        icon:"success"
		                    })
		                })
		                .catch(error => {
		                    EventBus.$emit('loader:hide')
		                })
					}
				})
			},
			reject(item,index){
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
							
							let url = `distributors/${item.customer.id}/return_items/${item.id}`
							let input = {
			                	status : 'Reject',
			                	reject_reason : reason,
			                }
			                Vue.axios.put(url, input)
			                .then(response => {
			                    swal({
			                        title:"Return Rejected!",
			                        icon:"success"
			                    })
			                    self.$refs.vuetable.tableData.splice(index,1)
			                })
			                .catch(error => {
			                	 EventBus.$emit('loader:hide')
			                })
						}
						else
						{
							swal('Please provide reason')
						}
					}
				})		
			},
			now(){
				return moment().format('X')
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
		    		to:((data.page-1) * this.perPage) + data.return_items.length
		    	}
		    	transformed.return_items = data.return_items
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
        		//this.number = ""
        		this.status = ""
        		this.selectedDealer = null
        		this.search()
        	},
		}
	}
</script>
<style scoped>
.product-category {
    display: block;
    color: #888;
    font-size: 11px;
}
</style>