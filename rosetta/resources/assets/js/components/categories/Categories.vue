<template>
	<div v-if="abilities.viewCategories">
		<section class="content-header">
			<h1>Categories <span class="pull-right"><button class="btn btn-sm btn-primary" @click="addCategory" v-if="abilities.createCategories">ADD</button></span></h1>
		</section>
		<section class="content">
			<category-list ref="categoryList"></category-list>
			<category-modal 
				ref="categoryModal" 
				@category:added="categoryAdded"
				v-if="abilities.createCategories">
			</category-modal>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import categoryList from './List'
	import categoryModal from './Modal'
	export default{
		mixins : [appMixin],
		components:{
			categoryList,
			categoryModal,
		},
		methods:{
			addCategory(){
				this.$refs.categoryModal.show()
			},
			categoryAdded(category){
				this.$refs.categoryList.reload()
			}
		}
	}
</script>