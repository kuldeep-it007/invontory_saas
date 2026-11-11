<template>
	<section class="content">
		<div class="row" v-if="!(isCustomer) && tiles">
			<div class="tile-stats" v-for="tile in tiles">	
				<div class="col-lg-4 col-xs-6">
		          	<div class="small-box bg-yellow">
		            	<div class="inner">
		              		<h3>{{tile.value}}</h3>
		              		<p>{{tile.title}}</p>
		            	</div>
		            	<div class="icon">
		              		<i class="far fa-thumbs-up"></i>
		            	</div>
		            	<router-link :to="{name:'pending-approval-dealers'}" class="small-box-footer">View <i class="fa fa-arrow-circle-right"></i></router-link>
		          	</div>
		        </div>
		    </div>
		</div>
		<div class="row" v-if="isCustomer && !(this.dealer && this.dealer.distributor_id != null)">
			<div class="tile-stats" v-for="tile in tiles">	
				<!--<div class="col-md-3 col-sm-4 col-xs-12">-->
				<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
		          	<div class="info-box">
		            	<span class="info-box-icon" :class="tile.colour""><i :class="tile.icon"></i></span>
		            	<div class="info-box-content">
		            		<span class="info-box-text">{{tile.title}}</span>
		              		<span class="info-box-number">{{tile.value}}</span>
		            	</div>
		            </div>
		        </div>
		    </div>
		</div>
		<div class="row" v-if="this.dealer && this.dealer.distributor_id != null">
			<div class="tile-stats" v-for="tile in tiles">	
				<!--<div class="col-md-3 col-sm-4 col-xs-12">-->
				<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
		          	<div class="info-box">
		            	<span class="info-box-icon" :class="tile.colour""><i :class="tile.icon"></i></span>
		            	<div class="info-box-content">
		            		<span class="info-box-text">{{tile.title}}</span>
		              		<span class="info-box-number">{{tile.value}}</span>
		            	</div>
		            </div>
		        </div>
		    </div>
		</div>
		<div class="row" v-if="isCustomer && !(this.dealer && this.dealer.distributor_id != null)">
			<div class="col-md-12">
				<h4>Current Month Complete Orders</h4>
			</div>
			<div class="tile-stats" v-for="tile in monthly">	
			<!--	<div class="col-md-3 col-sm-4 col-xs-12">-->
				<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
		          	<div class="info-box">
		            	<span class="info-box-icon" :class="tile.colour""><i :class="tile.icon"></i></span>
		            	<div class="info-box-content">
		            		<span class="info-box-text">{{tile.title}}</span>
		              		<span class="info-box-number">{{tile.value}}</span>
		            	</div>
		            </div>
		        </div>
		    </div>
		</div>
		<div class="row" v-if="this.dealer && this.dealer.distributor_id != null">
			<div class="col-md-12">
				<h4>Current Month Complete Orders</h4>
			</div>
			<div class="tile-stats" v-for="tile in monthly">	
			<!--	<div class="col-md-3 col-sm-4 col-xs-12">-->
				<div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
		          	<div class="info-box">
		            	<span class="info-box-icon" :class="tile.colour""><i :class="tile.icon"></i></span>
		            	<div class="info-box-content">
		            		<span class="info-box-text">{{tile.title}}</span>
		              		<span class="info-box-number">{{tile.value}}</span>
		            	</div>
		            </div>
		        </div>
		    </div>
		</div>
	</section>
</template>
<script>
	import Vue from 'vue'
	import AppMixins from '../vuex-mixin'
	export default{
		mixins: [AppMixins],
		data(){
			return {
				tiles:[],
				monthly:[],
			}
		},
		mounted(){
			let self = this
			Vue.axios.get('dashboard')
			.then(response => {
				self.tiles = response.data.tiles
				self.monthly = response.data.monthly
			})
			if(this.state.impersonated)
			{
				swal({
                    title: 'Impersonating',
                    text: `You are impersonating ${this.state.user.name}. Logout to continue as ${this.state.impersonated.user.name}`,
                    icon:'warning',
				})
			}
		},
	}
</script>
<style scoped>
.info-box-text{
	white-space: normal;
	text-transform: capitalize;
}
</style>
