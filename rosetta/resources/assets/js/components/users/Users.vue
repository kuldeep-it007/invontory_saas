<template>
	<div v-if="abilities.viewUser">
		<section class="content-header">
			<h1>Users 
				<!--<span class="pull-right">
				<router-link :to="{name:'users.create'}" class="btn btn-default">ADD</router-link></span>-->
			</h1>
		</section>
		<section class="content">
			<user-list></user-list>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import UserList from './List'
	export default{
		mixins : [appMixin],
		components:{
			UserList
		}
	}
</script>