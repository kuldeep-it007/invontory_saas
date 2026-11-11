<template>
	<div v-if="abilities.viewCollections">
		<section class="content-header">
			<h1>
				<router-link :to="{name:'categories'}">Categories</router-link> &raquo; {{decodeURIComponent($route.params.category_code)}} &raquo; Collections <span class="pull-right">
				<button class="btn btn-sm btn-primary" @click="addCollection" v-if="abilities.createCollections">ADD</button></span>
			</h1>
		</section>
		<section class="content">
			<collection-list ref="collectionList"></collection-list>
			<collection-modal
				ref="collectionModal" @collection:added="collectionAdded" v-if="abilities.createCollections"
				>
			</collection-modal>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import collectionList from './List'
	import collectionModal from './Modal'
	export default{
		mixins : [appMixin],
		components:{
			collectionList,
			collectionModal
		},
		methods:{
			addCollection(){
				this.$refs.collectionModal.show()
			},
			collectionAdded(){
				this.$refs.collectionList.reload()
			}
		}
	}
</script>