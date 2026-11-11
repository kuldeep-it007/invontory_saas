<template>
	<div v-if="abilities.manageProducts">
		<section class="content-header">
			<h1><template v-if="$route.params.id">Edit</template> <template v-else>Create</template> Product
				<template v-if="$route.params.id"> : {{product.nav_id}}</template>
			</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-12">
					<div class="box box-widget">
						<div class="box-header with-border">
              				<h3 class="box-title">General</h3>
            			</div>
						<div class="box-body">
							<div class="row">
								<div class="col-md-4">
									<div class="form-group" :class="errors.has('name')? 'has-error' : ''">
										<label>Name</label>
										<input class="form-control" v-model="product.name" v-validate="'required'" data-vv-name="name">
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group" :class="errors.has('description')? 'has-error' : ''">
										<label>Description</label>
										<input class="form-control" v-model="product.description" v-validate="'required'" data-vv-name="description">
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group" :class="errors.has('search_description')? 'has-error' : ''">
										<label>Search Description</label>
										<input class="form-control" v-model="product.search_description" v-validate="'required'" data-vv-name="search_description">
									</div>
								</div>
							</div>
							<div class="row" v-if="loaded">
								<div class="col-md-4">
									<div class="form-group" :class="errors.has('category')? 'has-error' : ''">
										<label>Category</label>
										<category 
											v-model="product.category" 
											ref="categorySelect"
											v-validate="'required'" 
											data-vv-name="category"></category>
										<!--<a href="#" @click.prevent="showCategoryModal">ADD NEW</a>-->
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group" :class="errors.has('collection')? 'has-error' : ''">
										<label>Collection</label>
										<collection v-model="product.collection" :category="product.category" @input="collectionSelected"  v-validate="'required'" data-vv-name="collection"></collection>
										<!--<a href="#" v-if="product.category">ADD NEW</a>-->
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label>Colour</label>
										<colour v-model="product.colour" :collection="product.collection"></colour>
										<!--<a href="#">ADD NEW</a>-->
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-4">
									<div class="form-group">
										<label>Base unit of measure</label>
										<base-unit-of-measure v-model="product.base_unit_of_measure"></base-unit-of-measure>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label>Purchase unit of measure</label>
										<purchase-unit-of-measure  v-model="product.purchase_unit_of_measure"></purchase-unit-of-measure>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label>Sales unit of measure</label>
										<sales-unit-of-measure v-model="product.sales_unit_of_measure"></sales-unit-of-measure>
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
						<div class="box-header with-border">
              				<h3 class="box-title">Technical specification</h3>
            			</div>
						<div class="box-body">
							<div class="row">
								<div class="col-md-4" v-for="techspec in techspecs" :key="techspec.attribute_id">
									<div class="form-group">
										<label>{{techspec.title}}</label>
										<input class="form-control" v-model="techspec.value">
									</div>
								</div>
							</div>
							<div class="row" v-if="techspecsStr.length > 250">
								<div class="col-md-12">
									<div class="alert alert-danger">
										Techspecs can not exceed 250 character limit set by NAV.
										<pre>{{techspecsStr}}</pre>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<!--
				<div class="col-md-6">
					<div class="box box-widget">
						<div class="box-header with-border">
              				<h3 class="box-title">Pricing (Region wise)</h3>
            			</div>
						<div class="box-body">
							<div class="row">
								<div class="col-md-4" v-for="region in regions" :key="region.id">
									<div class="form-group">
										<label>{{region.name}}</label>
										<input class="form-control" v-model.number="region.price">
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				-->
				<div class="col-md-6">
					<div class="box box-widget">
						<div class="box-header with-border">
              				<h3 class="box-title">Image</h3>
              			</div>
              			<div class="box-body">
              				<div class="row">
	              				<div class="col-md-12">
	              					<div class="form-group">
	              						<label>Image Class</label>
	              						<image-class v-model="product.image_class"></image-class>
	              					</div>
	              				</div>
	              				<div class="col-md-12">
	              					<product-image @image:changed="imageChanged" :logo="product.image"></product-image>
	              				</div>
	              			</div>
            			</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<button class="btn btn-primary btn-small" @click="add">
						<template v-if="$route.params.id">SAVE</template>
						<template v-else>ADD</template>
					</button>
				</div>
			</div>
			<product-result ref="productResult" @product-result:closed="productResultClosed"></product-result>
			<category-modal
				ref="categoryModal"
				@category:added="categoryAdded"
			></category-modal>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import Category from '../categories/Search'
	import Collection from '../collections/Search'
	import Colour from '../colour/Search'
	import BaseUnitOfMeasure from '../master/BaseUnitOfMeasure'
	import PurchaseUnitOfMeasure from '../master/PurchaseUnitOfMeasure'
	import SalesUnitOfMeasure from '../master/SalesUnitOfMeasure'
	import ImageClass from '../master/ImageClass'
	import ProductImage from '../Image'
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus'
	import ProductResult from './NewResultModal'
	import CategoryModal from '../categories/Modal'
	export default{
		mixins : [appMixin],
		components:{
			Category,
			Collection,
			Colour,
			BaseUnitOfMeasure,
			PurchaseUnitOfMeasure,
			SalesUnitOfMeasure,
			ImageClass,
			ProductImage,
			ProductResult,
			CategoryModal,
		},
		computed:{
			techspecsStr(){
				let str = ""
				_.each(this.techspecs,function(techspec){
					if(techspec.value)
					{
						str += `${techspec.title} : ${techspec.value}\r\n`
					}
				})
				return str.toUpperCase()
			}
		},
		data(){
			return {
				product:{
					id                      : null,
					nav_id                  : null,
					name                    : "",
					description             : "",
					search_description      : "",
					category                : null,
					collection              : null,
					colour                  : null,
					base_unit_of_measure    :null,
					sales_unit_of_measure   :null,
					purchase_unit_of_measure:null,
					image_class:null,
					image:null,
				},
				techspecs:[],
				regions:[],
				image:null,
				loaded:false
			}
		},
		mounted(){
			let self = this
			let axiosSpreads = [this.getTechSpecs(),this.getRegions()]
			if(this.$route.params.id)
			{
				axiosSpreads.push(this.getProduct())
			}
			EventBus.$emit('loader:show')
			Vue.axios.all(axiosSpreads)
			.then(Vue.axios.spread(function(techspecs,regions,product){
				EventBus.$emit('loader:hide')
				_.each(techspecs.data,function(element){
					self.techspecs.push(Object.assign(element,{value:"",id:null}))
				})
				//self.techspecs = techspecs.data
				_.each(regions.data,function(element){
					self.regions.push(Object.assign(element,{price:""}))
				})
				if(product)
				{
					self.product.name = product.data.name
					self.product.description = product.data.description
					self.product.search_description = product.data.search_description
					self.product.category = Object.assign({},product.data.category)
					self.product.collection = Object.assign({},product.data.collection)
					self.product.base_unit_of_measure = Object.assign({},{key:product.data.base_unit_of_measure,value:product.data.base_unit_of_measure})
					self.product.sales_unit_of_measure = Object.assign({},{key:product.data.sales_unit_of_measure,value:product.data.sales_unit_of_measure})
					self.product.purchase_unit_of_measure = Object.assign({},{key:product.data.purchase_unit_of_measure,value:product.data.
						purchase_unit_of_measure})
					self.product.nav_id = product.data.nav_id
					if(product.data.colour)
					{
						self.product.colour = Object.assign({},product.data.colour)
					}
					if(product.data.image)
					{
						self.product.image = product.data.image.url
						self.product.image_class = product.data.image.class
					}
				}
				self.loaded=true
				Vue.nextTick(function(){
					if(product)
						self.collectionSelected()	
				})
			}))

		},
		methods:{
			categoryAdded(category){
				this.$refs.categorySelect.addCategory(category)
			},
			showCategoryModal(){
				this.$refs.categoryModal.show()
			},
			productResultClosed(){
				this.$router.push({'name' : 'products'})
			},
			collectionSelected(){
				if(!this.product.collection)
					return
				let self = this
				//console.log(this.product.collection)
				Vue.axios.get(`categories/${encodeURIComponent(this.product.category.code)}/collections/${encodeURIComponent(encodeURIComponent(this.product.collection.code))}/techspecs`)
				.then(response => {
					_.each(this.techspecs,function(element){
						element.id = null
						element.value = ""
					})
					_.each(response.data,function(element){
						let techspec = _.findWhere(self.techspecs,{attribute_id:element.attribute_id})
						if(techspec)
						{
							techspec.id = element.id
							techspec.value = String(element.value).trim()
						}
					})
				})
			},
			imageChanged(image){
				this.image = image
			},
			getTechSpecs(){
				return Vue.axios.get('master/techspecs')
			},
			getRegions(){
				return Vue.axios.get('regions')
			},
			getProduct(){
				return Vue.axios.get(`products/${this.$route.params.id}`)
			},
			add(){
				let self = this
				let input = {}
				if(this.techspecsStr.length>250)
				{
					swal({
						title:"Error",
						text: "Techspecs can not exceed 250 character limit set by NAV.",
						icon:"error"
					})
					.then(v => {})
					.catch()
					return
				}
				input.name = this.product.name
				input.description = this.product.description
				input.search_description = this.product.search_description
				input.category = this.product.category.code
				input.collection = this.product.collection.code
				if(this.product.colour.code)
				{
					input.colour = this.product.colour.code	
				}
				input.base_unit_of_measure = this.product.base_unit_of_measure.key
				input.sales_unit_of_measure = this.product.sales_unit_of_measure.key
				input.purchase_unit_of_measure = this.product.purchase_unit_of_measure.key
				input.techspecs = []
				_.each(this.techspecs,function(element){
					if(element.value)
					{
						input.techspecs.push(element)
					}
				})
				//input.prices = []
				//_.each(this.regions,function(element){
				//	input.prices.push({region_id:element.id,price:element.price})
				//})
				if(this.$route.params.id)
				{
					input.nav_id = this.product.nav_id
				}
				input.image_class = this.product.image_class.id
				if(this.image)
				{
					input.image = this.image
				}
				
				this.$validator.validateAll()
				.then(value => {
					let method = 'post'
					let url = 'products'
					if(self.$route.params.id)
					{
						method = 'put'
						url = `products/${self.$route.params.id}`
					}
					if(value)
					{
						EventBus.$emit('loader:show')
						Vue.axios[method](url,input)
						.then(response => {
							EventBus.$emit('loader:hide')
							self.$refs.productResult.show(response.data)
						})
					}
					else
					{
						swal({
							title:"Error",
							text:"Please specify all required values",
							icon: "error"
						})
					}
				})
			}
		}
	}
</script>