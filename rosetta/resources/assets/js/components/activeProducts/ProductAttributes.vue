<template>
	<div v-if="abilities.viewActiveProductAttributes">
		<section class="content-header">
			<h1>Product Attributes
				<span class="pull-right">
					<span class="pull-right"><button class="btn btn-sm btn-primary" @click="addProductAttribute()" v-if="abilities.addActiveProductAttributes" :disabled="isCustomer">ADD</button></span>
				</span>
			</h1>
		</section>
		<section class="content">
			<product-attribute-list ref="ProductAttributeList"></product-attribute-list>
			 <add-product-attribute-modal 
                ref="addProductAttributeModal"
                @attribute-added="onAttributeAdded"
                :companyId="this.$route.params.id"
	            :distributorId="this.$route.params.distributorid"
	            :dealerId="this.$route.params.dealerid"				
				:superStockistId="this.$route.params.super_stockist_id">
            </add-product-attribute-modal>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import ProductAttributeList from './ProductAttributeList'
	import addProductAttributeModal from './AddProductAttributeModal'
	export default{
		mixins : [appMixin],
		components:{
			ProductAttributeList,
			addProductAttributeModal,
		},
		data(){
			return {
			}
		},
		mounted(){
		},
		methods:{
			addProductAttribute(){
	            this.$refs.addProductAttributeModal.show()
	        },
	        onAttributeAdded(attribute){
        		this.$refs.ProductAttributeList.reload()
            },
	    }
	}
</script>