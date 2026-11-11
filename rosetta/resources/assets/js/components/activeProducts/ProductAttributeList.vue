	<template>
	<section class="content">
		<div class="row">
			<div class="col-md-12">
				<div class="box box-widget">
					<div class="box-body">
						<div class="row">
							<div class="col-md-3">
								<!--<category v-model="category"></category>-->
								<category 
                                    v-model="category" 
                                    :distributor-id="this.$route.params.distributorid"
                                    :dealer-id="this.$route.params.dealerid"
                                    :company-id="this.$route.params.id"
                                ></category>
							</div>
							<div class="col-md-3">
								<collection v-model="collection" 
									:category="category"
									:distributor-id="this.$route.params.distributorid"
                                    :dealer-id="this.$route.params.dealerid"
                                    :company-id="this.$route.params.id"
								></collection>
							</div>
							<div class="col-md-3">
								<colour v-model="colour" 
									:collection="collection"
									:distributor-id="this.$route.params.distributorid"
                                    :dealer-id="this.$route.params.dealerid"
                                    :company-id="this.$route.params.id"
								></colour>
							</div>
							<div class="col-md-1">
								<button class="btn btn-default btn-block" @click="search"><i class="fa fa-search"></i></button>
							</div>
							<div class="clearfix"></div>
							<hr>
							
						</div>
						<vuetable 
							:fields="tableFields"
							:api-url="apiUrl"
							pagination-path="pagination"
							data-path="productAttributes"
							ref="vuetable"
							:css="css.table"
							:per-page="perPage"
							:append-params="appendParams"
							@vuetable:pagination-data="onPaginationData">
						<template slot="actions" slot-scope="props" >
	                        <template>
                                <a href="#" class="action text-green" v-if="abilities.updateActiveProductAttributes" @click.prevent="updateAttribute(props.rowData)"><i class="fa fa-pencil-alt"></i></a>
                                <a href="#" class="action text-red" v-if="abilities.deleteActiveProductAttributes" @click.prevent="deleteAttribute(props.rowData, props.rowIndex)"><i class="fa fa-trash-alt"></i></a>
                            </template>
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
		<attribute-modal
            ref="attributeModal"
            @attribute-update="onAttributeUpdate"
            :attribute="selectedAttribute"
            :companyId="this.$route.params.id"
            :distributorId="this.$route.params.distributorid"
            :dealerId="this.$route.params.dealerid">
        </attribute-modal>
	</section>
</template>
<script>
	import Vuetable from '../../vendor/vuetable2/Vuetable'
	import VuetablePagination from '../../vendor/vuetable2/VuetablePagination'
	import ProductAttributeListColumns from './ListFields.js'
	import vuetableCssConfig from '../../configs/VuetableCssConfig'
	import Vue from 'vue'
	import appMixin from '../../vuex-mixin.js'
	import Category from '../categories/Search'
	import Collection from '../collections/Search'
	import Colour from '../colour/Search'
	import attributeModal from './attributeModal'
	export default{
		mixins:[appMixin],
		components:{
			Vuetable,
			VuetablePagination,
			Category,
			Collection,
			Colour,
			attributeModal
		},
		data(){
			return {
				css:vuetableCssConfig,
				tableFields:ProductAttributeListColumns,
				perPage:parseInt(process.env.MIX_RECORDS_PER_PAGE),
				productAttributes:[],
				category:null,
				collection:null,
				colour:null,
				selectedAttribute:null,
				//apiUrl:`companies/${this.$route.params.id}/product_attributes`
			}
		},
		mounted(){
			//console.log(this.$route.params.id)
		},
		computed:{
			appendParams(){
				let params = {}
				if(this.category)
				{
					params.category = this.category.code
				}
				if(this.collection)
				{
					params.collection = this.collection.code
				}
				if(this.colour)
				{
					params.colour = this.colour.code
				}
				return params
			},
            apiUrl(){
            	if(this.$route.params.distributorid && this.$route.params.dealerid)
				{
					//return `companies/${this.$route.params.id}/distributors/${this.$route.params.distributorid}/dealers/${this.$route.params.dealerid}/product_attributes`
					return `companies/${this.$route.params.id}/dealers/${this.$route.params.dealerid}/product_attributes`
				}
            	else if(this.$route.params.distributorid)
            	{
            		return `companies/${this.$route.params.id}/distributors/${this.$route.params.distributorid}/product_attributes`
            	}
            	else if(this.$route.params.dealerid)
            	{
            		return `companies/${this.$route.params.id}/dealers/${this.$route.params.dealerid}/product_attributes`
            	}else if(this.$route.params.super_stockist_id)
            	{
            		return `companies/${this.$route.params.id}/super_stockists/${this.$route.params.super_stockist_id}/product_attributes`
            	}	
				return `companies/${this.$route.params.id}/product_attributes`
            },
		},
		methods:{
			search(){
				this.$refs.vuetable.refresh()
			},
			reload(){
				this.$refs.vuetable.refresh()
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
		    		to:((data.page-1) * this.perPage) + data.productAttributes.length
		    	}
		    	transformed.productAttributes = data.productAttributes
		    	return transformed
		    },
		    
		    onChangePage (page) {
		      this.$refs.vuetable.changePage(page)
		    },
		    updateAttribute(attribute){
                this.selectedAttribute = attribute
                this.$refs.attributeModal.show(attribute)
            },
            onAttributeUpdate(attribute, $refresh){
            	if($refresh == true)
            		this.$refs.vuetable.refresh()
                else
                	this.attribute = attribute
            },
            deleteAttribute(attribute, index)
            {
            	let self = this
            	let url = ''
            	let input = {}
            	let distributorId = self.$route.params.distributorid
            	let dealerId = self.$route.params.dealerid
            	input.distributorId = distributorId
                input.dealerId = dealerId
                let $title = null
                if(attribute.colour_code)
                {
                    if(!(distributorId) && !(dealerId))
                    {
                    	$title = `All Products with Colour: ${attribute.colour_code} will be deactivated from the Company. None of the Customers can see/purchase corresponding products. Press OK if you wish to go ahead with deactivation.`
                    	input.deleteAll = true
                    }
                    else
                    {
						$title = `This Customer will not be able see/purchase products corresponding to Colour: ${attribute.colour_code}. Press OK if you wish to go ahead with deactivation.`
                    }
                    url = `companies/${self.$route.params.id}/product_attribute_colours/${attribute.id}`
                }
                if(!attribute.colour_code)
                {
            		if(!(distributorId) && !(dealerId))
            		{
            			$title = `All Products with Category: ${attribute.category_code}, Collection: ${attribute.collection_code} & subsequent colours (if any) will be deactivated from the Company. None of the Customers can see/purchase corresponding products. Press OK if you wish to go ahead with deactivation.`
            			input.deleteAll = true
            		}
                	else
                	{
                		$title = `This Customer will not be able see/purchase products corresponding to Category: ${attribute.category_code}, Collection: ${attribute.collection_code}  & subsequent colours (if any). Press OK if you wish to go ahead with deactivation.`
                	}
                	url = `companies/${self.$route.params.id}/product_attributes/${attribute.id}`
                }
                swal({
                    title: "Deactivate",
                    text: $title,
                    icon:'warning',
                    buttons: {
                        cancel: true,
                        confirm: true,
                    },
                })
                .then(value => {
                    if(!value)
                        return;
                    Vue.axios.delete(url,{data:input})
	                .then(response => {
	                    swal({
	                        title:"Attribute deleted!",
	                        icon:"success"
	                    })
	                    .then(value => {
	                    	this.$refs.vuetable.refresh()
	                    }) 
	                })
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