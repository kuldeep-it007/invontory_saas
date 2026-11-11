<template>
	<div v-if="abilities.viewUser">
		<section class="content-header">
			<h1>Users
			<!--	<router-link class="small-box-footer" :to="{name:'companies.distributors',params:{id:$route.params.id}}">Distributor</router-link>  &raquo; Users -->
				<span class="pull-right">
					<span class="pull-right"><button class="btn btn-sm btn-primary" @click="addUser()" v-if="abilities.createUser">ADD</button></span>
				</span>
			<!--	<span class="pull-right"><router-link :to="{name:'companies.distributors.users.create'}" class="btn btn-default">ADD</router-link></span>-->
			</h1>
		</section>
		<section class="content">
			<user-list ref="UserList"></user-list>
			<add-user-modal 
                ref="addUserModal"
                @user-added="onUserAdded"
                :companyId="this.$route.params.id"
	            :distributorId="this.$route.params.distributorid"
	            :dealerId="this.$route.params.dealerid"
	            :super_stockist_id="this.$route.params.super_stockist_id">
            </add-user-modal>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import UserList from './List'
	import addUserModal from './AddUserModal'
	export default{
		mixins : [appMixin],
		components:{
			UserList,
			addUserModal
		},
		methods:{
			addUser(){
	            this.$refs.addUserModal.show()
	        },
	        onUserAdded(user){
        		this.$refs.UserList.reload()
            },
	    }
	}
</script>