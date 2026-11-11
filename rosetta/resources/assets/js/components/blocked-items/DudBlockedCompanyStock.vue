<template>
	<div v-if="abilities.viewDudBlockedItems">
		<section class="content-header">
			<h1>Company - Blocked Stock</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-body">
							<div class="row">
								<div class="col-md-12 table-responsive">
									<vuetable 
										:fields="tableFields"
										:api-url="apiUrl"
										pagination-path="pagination"
										data-path="items"
										:per-page="perPage"
										ref="vuetable"
										:css="css.table"
										@vuetable:pagination-data="onPaginationData"
										:load-on-start="false"
										>
										<template slot="actions" slot-scope="props" >
							        		<div class="custom-actions">
							        			<a href="#" @click.prevent="deleteDiscount(props.rowData,props.rowIndex)"><i class="fa fa-times"></i></a>
							        		</div>
							      		</template>
							      		<template slot="category" slot-scope="props" >
							      			<template v-if="props.rowData.product.category.code">
							      				{{props.rowData.product.category.display_name}}
							      			</template>
							      		</template>
							      		<template slot="collection" slot-scope="props" >
							      			<template v-if="props.rowData.product.collection.code">
							      				{{props.rowData.product.collection.display_name}}
							      			</template>
							      		</template>
							      		<template slot="colour" slot-scope="props" >
							      			<template v-if="props.rowData.product.colour.code">
							      				{{props.rowData.product.colour.display_name}}
							      			</template>
							      		</template>
							      		<template slot="customer" slot-scope="props" >
							      			<template v-if="props.rowData.customer">
							      				{{props.rowData.customer.name}} ({{props.rowData.customer.nav_id}})
							      			</template>
							      		</template>
							      		<template slot="quantity" slot-scope="props" >
                                        	{{props.rowData.order_quantity}} <sup>{{props.rowData.product.base_unit_of_measure}}</sup> <span class="label label-default pull-right">{{props.rowData.rate_type}}</span>
                                    	</template>
                                    	<template slot="expires" slot-scope="props" >
                                        	<vue-countdown :time="(props.rowData.expires - now()) * 1000" :interval="1000">
    											<template slot-scope="props">Expires {{props.totalHours }}:{{ props.minutes }}:{{ props.seconds }}</template>
  											</vue-countdown>
                                    	</template>
                                    	<template slot="actions" slot-scope="props" >
                                        <div class="custom-actions">
                                        	<a href="#" @click.prevent="unblock(props.rowData,props.rowIndex)"><i class="fa fa-lock-open"></i></a>
                                            
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
	import RegionSelect from '../region/Select'
	import Company from '../companies/Select'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import BlockedListColumns from './ListFields.js'
	import VueCountdown from '@xkeshi/vue-countdown'
	import {EventBus} from '../../configs/EventBus'
	import Vue from 'vue'
	export default{
		mixins : [appMixin],
		components:{
			RegionSelect,
			Company,
			VuetablePagination,
			Vuetable,
			VueCountdown
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:BlockedListColumns,
				perPage:100,
				region:null,
				company:null,
				sideBarWasOpen:false,
			}
		},
		computed:{
			apiUrl(){
				if(this.company)
					return `companies/${this.company.id}/distributors/${this.distributor.id}/dud_blocked_company_items`
				return null
			}
		},
		mounted(){
			this.$refs.vuetable.hideField(5)
			if(this.distributor)
			{
				this.company = 	Object.assign({},this.distributor.company)
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
			unblock(item,index){
				let self = this
				let url = ""
				if(item.customer.type == 'DEALER')
				{
					url = `companies/${this.company.id}/dealers/${item.customer.id}/unBlock_items`
				}
				swal({
		    		title: 'Unblock item?',
		    		icon:'warning',
  					buttons: {
    					cancel: true,
    					confirm: true,
  					},
				})
				.then(value => {
					if(value)
					{
						EventBus.$emit('loader:show')
		                Vue.axios.post(url,{blocked_Ids:[item.id]})
		                .then(response => {
		                    EventBus.$emit('loader:hide')
		                    self.$refs.vuetable.tableData.splice(index,1)
		                    swal({
		                        title:"Item unblocked",
		                        icon:"success"
		                    })
		                })
		                .catch(error => {
		                    EventBus.$emit('loader:hide')
		                })
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
		    		to:((data.page-1) * this.perPage) + data.items.length
		    	}
		    	transformed.items = data.items
		    	return transformed
		    },
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
		    }
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