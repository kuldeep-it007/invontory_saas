<template>
	<div>
		<section class="content-header">
			<h1>Search Products</h1>
		</section>
		<section class="content">
			<div class="row">
				<div class="col-md-3">
					<div class="form-group">
      					<label>Category</label>
      					<category-search v-model="category"></category-search>
      				</div>
				</div>
				<div class="col-md-3">
					<div class="form-group">
      					<label>Collection</label>
      					<collection-search v-model="collection" :category="category"></collection-search>
      				</div>
				</div>
			</div>
		</section>
	</div>
</template>
<script>
	import CategorySearch from '../categories/Search'
	import CollectionSearch from '../collections/Search'
	export default {
		data(){
			return {
				category:null,
				collection:null,
			}
		},
		components:{
			CategorySearch,
			CollectionSearch
		},
		computed: {
            state () {
                return this.store.state
            },
            store () {
                return this.$store
            }
        }
	}
</script>