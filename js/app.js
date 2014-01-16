var app = angular.module('app', ['ngRoute']);

app.config(function($routeProvider) {
	$routeProvider
		.when('/login', {templateUrl: '/templates/login.html', controller: loginCtrl})
		.when('/register', {templateUrl: '/templates/register.html', controller: registerCtrl})
		.when('/:id', {templateUrl: '/templates/article.html', controller: articleCtrl})
		.when('/', {templateUrl: '/templates/articles.html', controller: articlesCtrl})
		.otherwise({redirectTo: '/'})
	;
});

app
	.service('AuthService', function($http, $location, $rootScope) {
		return {
			me: function() {
				$http
					.get('/me')
					.success(function(r) {
						if(r.id) {
							$rootScope.me = r;
							return r;
						}else{
							delete $rootScope.me;
							return false;
						}
					})
				;
			},
			getToken: function(scope) {
				$http
					.get('/token')
					.success(function(r) {
						scope.token=r;
					})
				;
			},
			mustBeGuest: function() {
				$http
					.get('/me')
					.success(function(r) {
						if(r.id) $location.path('/');
					})
				;
			},
			mustBeMember: function() {
				$http
					.get('/me')
					.success(function(r) {
						if(!r.id) $location.path('/login');
					})
				;
			}
		};
	})
	.service('FlashService', function($rootScope, $timeout) {
		return {
			show: function(msg, cls) {
				cls = cls!==undefined ? cls : 'info';
				var time = 2500;
				$rootScope.flash = {msg:msg, cls:cls}
				$timeout(function() {
					delete $rootScope.flash;
				}, time);
			}
		};
	})
	.run(function($rootScope, AuthService) {
		$rootScope.$on('$locationChangeStart', function(e,n,o) {
			AuthService.me(e.currentScope);
			window.s = $rootScope;
		});
	})
;

//CONTROLLERS--------------------------------------------------------------------------------------
//AUTH
function loginCtrl($scope, $http, $location, AuthService, FlashService) {
	AuthService.mustBeGuest();
	AuthService.getToken($scope);
	$scope.login = function() {
		$http
			.post('/login', {email: $scope.email, password: $scope.password, csrf_token: $scope.token})
			.success(function(r) {
				FlashService.show(r, 'success');
				$location.path('/');
			})
			.error(function(r) {
				FlashService.show(r, 'danger');
			})
		;
	};
}

function registerCtrl($scope, $http, $location, AuthService, FlashService) {
	AuthService.mustBeGuest();
	AuthService.getToken($scope);
	$scope.register = function() {
		console.log($scope);
		$http
			.post('/register', {email: $scope.email, password: $scope.password, csrf_token: $scope.token})
			.success(function(r) {
				FlashService.show(r, 'success');
				$location.path('/login');
			})
			.error(function(r) {
				FlashService.show(r, 'danger');
			})
		;
	};
}


//ARTICLES
function articlesCtrl($scope, $http, AuthService) {
	AuthService.mustBeMember();
	$http
		.get('/articles?r='+Math.floor(Math.random()*100000))
		.success(function(r) { $scope.articles = r })
	;
	$scope.title = 'Article list';

}

function articleCtrl($scope, $http, $route, AuthService) {
	AuthService.mustBeMember();
	$http
		.get('/article/'+$route.current.params.id+'?r='+Math.floor(Math.random()*100000))
		.success(function(r) {
			$scope.title = r[0].title;
			$scope.content = r[0].content;
		})
		.error(function(r) {
			$scope.title = '404';
			$scope.content = r;
		})
	;
}