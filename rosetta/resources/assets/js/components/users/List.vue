<template>
	<div class="row">
		<div class="col-md-12">
			<div class="box box-widget">
				<div class="box-body table-responsive">
					<vuetable 
						:fields="tableFields"
						api-url="users"
						pagination-path="pagination"
						data-path="users"
						:per-page="perPage"
						ref="vuetable"
						:css="css.table"
						@vuetable:pagination-data="onPaginationData">
						<template slot="actions" slot-scope="props" v-if="abilities.updateUser">
			        		<div class="custom-actions">
			        			<button class="btn btn-xs btn-info" @click="deleteUser(props.rowData,props.rowIndex)" :disabled="state.user.email === props.rowData.email"><i class="fa fa-trash-alt"></i></button>
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
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import UserListColumns from './ListColumns.js'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import {EventBus} from '../../configs/EventBus'
	import Vue from 'vue'
	export default{
		mixins : [appMixin],
		components:{
			Vuetable,
			VuetablePagination,
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:UserListColumns,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				users:[]
			}
		},
		mounted(){
			if(this.isCustomer || this.state.user.company)
			{
				this.$refs.vuetable.hideField(2)
				this.$refs.vuetable.hideField(3)
				this.$refs.vuetable.hideField(4)
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
		    		to:((data.page-1) * this.perPage) + data.users.length
		    	}
		    	transformed.users = data.users
		    	return transformed
		    },
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
		    },
		    deleteUser(user,index){
				let self = this;
				swal({
		    		title: `Delete  user ${user.name}`,
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
						Vue.axios.delete(`users/${user.id}`)
						.then(response => {
							EventBus.$emit('loader:hide')
							self.$refs.vuetable.tableData.splice(index,1)
						})
						.catch(error => {
							if (error.response.status === 412) 
							{
        						swal ( "Error" ,  error.response.data ,  "error" )
		    					.then(value =>{
		    						self.$router.push({name:'users'})
		    					})
        					}
		                })
					}
				})
			}
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