<template>
	<div class="row">
		<div class="col-md-12">
			<div class="box box-widget">
				<div class="box-body table-responsive">
					<vuetable 
						:fields="tableFields"
						:api-url="apiUrl"
						pagination-path="pagination"
						data-path="users"
						:per-page="perPage"
						ref="vuetable"
						:css="css.table"
						track-by="id"
						@vuetable:pagination-data="onPaginationData">
						<template slot="actions" slot-scope="props" v-if="abilities.updateUser">
			        		<div class="custom-actions">
			        			<button class="btn btn-xs btn-default" @click="impersonate(props.rowData)" v-if="abilities.impersonate"><i class="fa fa-user-secret"></i></button>
			        			<button class="btn btn-xs btn-info" @click="resetPassword(props.rowData)"><i class="fas fa-key"></i></button>
			        			<button class="btn btn-xs btn-success" @click="updateUser(props.rowData,props.rowIndex)" :disabled="state.user.email === props.rowData.email"><i class="fa fa-pencil-alt"></i></button>
			        			<button class="btn btn-xs btn-danger" @click="deleteUser(props.rowData,props.rowIndex)" :disabled="state.user.email === props.rowData.email"><i class="fa fa-trash-alt"></i></button>
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
		<reset-password-modal ref="resetPasswordModal"></reset-password-modal>
		<update-user-modal 
			ref="updateUserModal" 
			@user-updated="onUserUpdated">
		</update-user-modal>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import UserListColumns from './ListColumns.js'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import {EventBus} from '../../configs/EventBus'
	import ResetPasswordModal from './ResetPasswordModal'
	import UpdateUserModal from './UpdateUserModal'
	import Vue from 'vue'
	export default{
		mixins : [appMixin],
		components:{
			Vuetable,
			VuetablePagination,
			ResetPasswordModal,
			UpdateUserModal,
		},
		computed:{
            apiUrl(){
            	let url = ""
				if(this.$route.meta.type == 'distributor')
				{
					url = `companies/${this.$route.params.id}/distributors/${this.$route.params.distributorid}/users`
				}
				if(this.$route.meta.type == 'dealer')
				{
					url = `companies/${this.$route.params.id}/dealers/${this.$route.params.dealerid}/users`
				}
				if(this.$route.meta.type == 'dealerUnderDistributor')
				{
					url = `dealers/${this.$route.params.dealerid}/users`
				}
				if(this.$route.meta.type == 'superStockist')
				{
					url = `companies/${this.$route.params.id}/super_stockists/${this.$route.params.super_stockist_id}/users`
				}
				if(this.$route.meta.type == 'distributorUnderSuperstockist')
				{
					url = `companies/${this.state.superStockist.company.id}/distributors/${this.$route.params.distributorid}/users`
				}
				return url
            },
        },
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:UserListColumns,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				users:[],
				selectedUser:null,
			}
		},
		methods:{
			impersonate(user){
				this.$router.push({name:'users.impersonate',params:{userid:user.id}})
			},
			resetPassword(user){
                this.$refs.resetPasswordModal.show(user)
            },
            updateUser(user){
            	let self = this
            	self.selectedUser = Object.assign({},user)
            	self.$refs.updateUserModal.show(user)
            },//#751
            onUserUpdated(email,phone){
            	let user = _.findWhere(this.$refs.vuetable.tableData,{id:this.selectedUser.id})
            	user = Object.assign(user,{email:email,phone:phone})
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
		    		to:((data.page-1) * this.perPage) + data.users.length
		    	}
		    	transformed.users = data.users
		    	return transformed
		    },
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
		    },
		    reload(){
				this.$refs.vuetable.refresh()
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
						let url = ""
						if(self.$route.meta.type == 'distributor')
						{
							url = `companies/${self.$route.params.id}/distributors/${self.$route.params.distributorid}/users/${user.id}`
						}
						if(self.$route.meta.type == 'dealer')
						{
							url = `companies/${self.$route.params.id}/dealers/${self.$route.params.dealerid}/users/${user.id}`
						}
						if(self.$route.meta.type == 'dealerUnderDistributor')
						{
							url = `dealers/${self.$route.params.dealerid}/users/${user.id}`
						}
						if(self.$route.meta.type == 'superStockist' || self.$route.meta.type == 'distributorUnderSuperstockist')
						{
							url = `companies/${self.$route.params.id}/super_stockists/${self.$route.params.super_stockist_id}/users/${user.id}`
						}
						EventBus.$emit('loader:show')
						Vue.axios.delete(url)
						.then(response => {
							EventBus.$emit('loader:hide')
							self.$refs.vuetable.tableData.splice(index,1)
						})
						.catch(error => {
							if (error.response.status === 412) 
							{
        						swal ( "Error" ,  error.response.data ,  "error" )
		    					.then(value =>{
		    						if(self.$route.meta.type == 'distributor')
		    						{
		    							self.$router.push({name:'companies.distributors.users',params:{id:self.$route.params.id,distributorid:self.$route.params.distributorid}})
		    						}
		    						if(self.$route.meta.type == 'dealer')
		    						{
		    							self.$router.push({name:'companies.dealers.users',params:{id:self.$route.params.id,dealerid:self.$route.params.dealerid}})
		    						}
		    						if(self.$route.meta.type == 'dealerUnderDistributor')
		    						{
		    							self.$router.push({name:'my-dealers.users',params:{dealerid:self.$route.params.dealerid}})
		    						}
		    						if(self.$route.meta.type == 'superStockist')
		    						{
		    							self.$router.push({name:'companies.super_stockists.users',params:{id:self.$route.params.id,super_stockist_id:self.$route.params.super_stockist_id}})
		    						}
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