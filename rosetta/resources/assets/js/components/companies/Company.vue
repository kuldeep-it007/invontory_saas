<template>
	<div v-if="company">
		<section class="content-header">
			<h1>{{company.name}} <small>{{company.region.name}}</small></h1>
		</section>
		<section class="content">
			<div class="row">
        		<div class="col-lg-3 col-xs-6">
          			<div class="small-box bg-aqua">
            			<div class="inner">
              				<h3>{{company.distributors}}</h3>
              				<p>Distributors</p>
            			</div>
            			<div class="icon">
              				<i class="fa fa-users"></i>
            			</div>
            			<router-link class="small-box-footer" :to="{name:'companies.distributors',params:{id:company.id}}">View distributors <i class="fa fa-arrow-circle-right"></i></router-link>
          			</div>
        		</div>

        		<div class="col-lg-3 col-xs-6">
          			<div class="small-box bg-green">
            			<div class="inner">
              				<h3>{{company.dealers}}</h3>
              				<p>Dealers</p>
            			</div>
            			<div class="icon">
              				<i class="fa fa-users"></i>
            			</div>
            			<a href="#" class="small-box-footer" @click.prevent>View dealers <i class="fa fa-arrow-circle-right"></i></a>
          			</div>
        		</div>

        		<div class="col-lg-3 col-xs-6">
          			<div class="small-box bg-yellow">
            			<div class="inner">
              				<h3>{{company.retailCustomers}}</h3>
              				<p>Retail Customers</p>
            			</div>
            			<div class="icon">
              				<i class="fa fa-users"></i>
            			</div>
                  <router-link class="small-box-footer" :to="{name:'RetailCustomers',params:{id:company.id}}">View retail customers <i class="fa fa-arrow-circle-right"></i></router-link>
          			</div>
        		</div>
      		</div>
      		<inventory :company="company"></inventory>

		</section>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import Vue from 'vue'
	import Inventory from '../inventory/List'
	export default{
		mixins : [appMixin],
		components:{
			Inventory
		},
		data(){
			return {
				company:null
			}
		},
		mounted(){
			let self = this
			console.log(this.$route.params.id)
			if(this.$route.params.id)
			{
				Vue.axios.get(`companies/${this.$route.params.id}/dashboard`)
				.then(response => {
					self.company = Object.assign({},response.data)

				})
			}
		}
	}
</script>