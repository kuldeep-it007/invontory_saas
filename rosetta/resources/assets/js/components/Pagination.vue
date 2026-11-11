<template>
	<ul class="pagination">
    	<li v-bind:class="{disabled:onFirstPage}">
    		<a @click="goToFirstPage">First</a>
    	</li>
    	<li v-bind:class="{disabled:onFirstPage}">
    		<a  @click="goToPrevPage">Prev</a>
    	</li> 
    	<li>
    		<div class="input-group">
  				<input type="number" v-model="currentPage" @input="pageInputChanged">
  				<span class="input-group-addon" v-if="totalPages">/ {{totalPages}}</span>
			</div>
    	</li> 
    	<li v-bind:class="{disabled:onLastPage}">
    		<a @click="goToNextPage">Next</a>
    	</li> 
    	<li v-bind:class="{disabled:onLastPage}">
    		<a @click="goToLastPage">Last</a>
    	</li>
    </ul>
</template>
<script>
	export default {
		data(){
			return {
					currentPage: 1,
					totalPages : 10
				};
		},
		computed:{
			onFirstPage (){
				if(this.currentPage == 1)
				{
					return true;
				}
				return false;
			},
			onLastPage (){
				if(this.currentPage == this.totalPages)
				{
					return true;
				}
				return false;
			}
		},
		methods:{
			pageInputChanged (){
				if(this.currentPage == "")
					return;
				if(this.currentPage <=0)
					this.currentPage = 1;
				if(this.currentPage>this.totalPages)
					this.currentPage = this.totalPages;
				this.loadPage();
			},
			goToFirstPage (){
				this.currentPage = 1
				this.loadPage();
			},
			goToPrevPage (){
				if(this.currentPage>1)
					this.currentPage--;
				this.loadPage();
			},
			goToNextPage (){
				if(this.currentPage<this.totalPages)
				this.currentPage++;
			this.loadPage();
			},
			goToLastPage (){
				this.currentPage = this.totalPages;
				this.loadPage();
			},
			loadPage (){
				this.$emit('pagination:change-page',this.currentPage);
			},
			setPaginationData (pagination){
				this.currentPage = pagination.current_page;
				this.totalPages = pagination.last_page;
			}
		}
	}
</script>
<style scoped>
.pagination {
    	font-size: 12px;
    	color:red;
    }
    .pagination .input-group {
    	float: left;
	}
	.pagination .input-group {
    	position: relative;
    	display: table;
    	border-collapse: separate;
    	width: 1%;
	}
	.pagination .input-group-addon:last-child {
    	border-left: 0;
	}
	.pagination .input-group .form-control:last-child, .pagination .input-group-addon:last-child, .pagination .input-group-btn:first-child>.btn-group:not(:first-child)>.btn, .pagination .input-group-btn:first-child>.btn:not(:first-child), .pagination .input-group-btn:last-child>.btn, .pagination .input-group-btn:last-child>.btn-group>.btn, .pagination .input-group-btn:last-child>.dropdown-toggle {
    	border-top-left-radius: 0;
    	border-bottom-left-radius: 0;
	}
	.pagination .input-group-addon {
	    padding: 6px 12px;
	    font-size: 14px;
	    font-weight: 400;
	    line-height: 1;
	    color: #555;
	    text-align: center;
	    background-color: #eee;
	    border: 1px solid #ccc;
	    border-radius: 4px;
	}

	.pagination .input-group-addon, .pagination .input-group-btn {
	    width: 1%;
	    white-space: nowrap;
	    vertical-align: middle;
	}

	.pagination .input-group .form-control, .pagination .input-group-addon, .input-group-btn {
	    display: table-cell;
	}
	.pagination .input-group .form-control:not(:first-child):not(:last-child), .pagination .input-group-addon:not(:first-child):not(:last-child), .pagination .input-group-btn:not(:first-child):not(:last-child) {
	    border-radius: 0;
	}

	.pagination .input-group .form-control, .pagination .input-group-addon, .pagination .input-group-btn {
	    display: table-cell;
	}

	.pagination .input-group .form-control {
	    position: relative;
	    z-index: 2;
	    float: left;
	    width: 100%;
	    margin-bottom: 0;
	}

	.pagination .form-control {
	    display: block;
	    width: 100%;
	    height: 34px;
	    padding: 6px 12px;
	    font-size: 14px;
	    line-height: 1.42857143;
	    color: #555;
	    background-color: #fff;
	    background-image: none;
	    border: 1px solid #ccc;
	    border-radius: 4px;
	    -webkit-box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
	    box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
	    -webkit-transition: border-color ease-in-out .15s,-webkit-box-shadow ease-in-out .15s;
	    -o-transition: border-color ease-in-out .15s,box-shadow ease-in-out .15s;
	    transition: border-color ease-in-out .15s,box-shadow ease-in-out .15s;
	}
	.pagination .input-group-addon, .pagination .input-group-btn {
	    width: 1%;
	    white-space: nowrap;
	    vertical-align: middle;
	    border-color: #dddddd;
	    border-radius: 0;
	    font-size: 12px;
	}
	.pagination input {
	    width: 60px;
	    line-height: 1.4;
	    margin-bottom: 0;
	    height: 31px;
	    border-radius: 0;
	    border-left: 0;
	    border-color: #dddddd;
	    text-align: center;
	    font-size: 12px;
	    outline: none;
	}
	.pagination input:focus{
		border-color: #dddddd;
	    outline: none;
	     -webkit-box-shadow:none;
	    -moz-box-shadow:none;
	    box-shadow:none;
	}

	.pagination .input-group{
		float:left;
	}
	.pagination input[type=number]::-webkit-inner-spin-button, 
	.pagination input[type=number]::-webkit-outer-spin-button { 
	  -webkit-appearance: none; 
	  margin: 0; 
	}
	.pagination li a{
		cursor: pointer;
	}
</style>