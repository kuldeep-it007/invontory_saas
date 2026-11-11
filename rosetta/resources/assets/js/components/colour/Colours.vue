<template>
	<div v-if="abilities.viewColours">
		<section class="content-header">
			<h1>
				<router-link :to="{name:'categories'}">Categories</router-link> &raquo; {{decodeURIComponent($route.params.category_code)}} &raquo; 
				<router-link :to="{name:'categories.collections'}">Collections</router-link> &raquo; {{decodeURIComponent($route.params.collection_code)}} &raquo; Colours 
				<span class="pull-right">
					<button class="btn btn-sm btn-primary" @click="addColour" v-if="abilities.createColours">ADD</button></span>
				</span>
			</h1>
		</section>
		<section class="content">
			<colour-modal ref="colourModal" @colour:added="colourAdded" v-if="abilities.createColours"></colour-modal>
			<colour-list ref="colourList"></colour-list>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import colourList from './List'
	import colourModal from './Modal'
	export default{
		mixins : [appMixin],
		components:{
			colourList,
			colourModal
		},
		methods:{
			addColour(){
				this.$refs.colourModal.show()
			},
			colourAdded(){
				this.$refs.colourList.reload()
			}
		}
	}
</script>