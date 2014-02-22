var app = angular.module('app', ['ngRoute', 'ngCookies']);

app
.config(function ($routeProvider) {
	$routeProvider
		.when('/', {templateUrl: '/templates/home.html', controller: 'home'})
		.when('/login', {templateUrl: '/templates/login.html', controller: 'login'})
		.when('/logout', {template: '..', controller: 'logout'})
		.when('/reset-password', {templateUrl: '/templates/reset_password.html', controller: 'resetPassword'})
		.when('/register', {templateUrl: '/templates/register.html', controller: 'register'})
		.when('/profile', {templateUrl: '/templates/profile.html', controller: 'profile'})
		.when('/edit', {templateUrl: '/templates/profile_edit.html', controller: 'profileEdit'})
		.when('/user/:id', {templateUrl: '/templates/user.html', controller: 'user'})
		.otherwise({redirectTo: '/'})
	;
})
.service('AuthService', function ($http, $location, $rootScope, $q) {
	return {
		me: function () {
			var deferred = $q.defer();
			$http
				.get('/api/me')
				.success(function (r) {
					deferred.resolve(r);
					$rootScope.me = r;
				})
				.error(function (r) {
					delete $rootScope.me;
				})
			;
			return deferred.promise;
		},
		setToken: function (scope) {
			$http
				.get('/api/token')
				.success(function (r) {
					scope.token=r[0];
				})
			;
		},
		checkPermision: function (expectedRole) {
			var expectedRole = expectedRole !== undefined ? expectedRole : 'user';
			var userRole = 'guest';
			$http
				.get('/api/me')
				.success(function (r) {
					if (r.role) userRole = r.role;
					if (userRole != expectedRole) $location.path('/');
				})
				.error(function (r) {
					if (userRole != expectedRole) $location.path('/');
				})
			;
		}
	};
})
.service('FlashService', function ($rootScope, $timeout, $cookieStore, $cookies) {
	return {
		show: function (msg, cls) {
			cls = cls!==undefined ? cls : 'info';
			var time = 2500;
			$rootScope.flash = {msg:msg, cls:cls}
			$timeout(function () {
				delete $rootScope.flash;
			}, time);
		},
		checkCookies: function () {
			if ($cookies.flashCookie) {
				console.log($cookies.flashCookie);
				var flashCookie = eval($cookies.flashCookie.replace(/\+/g,' '));
				if (flashCookie.length == 2) {
					this.show(flashCookie[0], flashCookie[1]);
					$cookieStore.remove('flashCookie');
				}
			}
		}
	};
})
.controller('home', function () {})
.controller('login', function ($scope, $http, $location, AuthService, FlashService) {
	AuthService.checkPermision('guest');
	AuthService.setToken($scope);
	$scope.login = function () {
		$http
			.post('/api/login', {email: $scope.email, password: $scope.password, csrf_token: $scope.token})
			.success(function (r) {
				FlashService.show(r[0], 'success');
				$location.path('/');
			})
			.error(function (r) {
				FlashService.show(r[0], 'danger');
			})
		;
	};
})
.controller('logout', function ($http, FlashService, $location) {
	//$location.path('/api/logout');
	$http
		.get('/api/logout')
		.success(function (r) {
			FlashService.show(r[0], 'success');
			$location.path('/');
		})
		.error(function (r) {
			FlashService.show(r[0], 'danger');
			$location.path('/');
		})
	;
})
.controller('register', function ($scope, $http, $location, AuthService, FlashService) {
	AuthService.checkPermision('guest');
	AuthService.setToken($scope);
	$scope.register = function () {
		$http
			.post('/api/register', {email: $scope.email, password: $scope.password, name: $scope.name, surname: $scope.surname, csrf_token: $scope.token})
			.success(function (r) {
				FlashService.show(r[0], 'success');
				$location.path('/');
			})
			.error(function (r) {
				FlashService.show(r[0], 'danger');
			})
		;
	};
})
.controller('resetPassword', function ($scope, $http, AuthService, FlashService) {
	AuthService.checkPermision('guest');
	AuthService.setToken($scope);
	$scope.reset = function () {
		$http
			.post('/api/reset-password', {email: $scope.email, csrf_token: $scope.token})
			.success(function (r) {
				FlashService.show(r[0], 'success');
			})
			.error(function (r) {
				FlashService.show(r[0], 'danger');
			})
		;
	};
})
.controller('profile', function ($scope, $http, AuthService) {
	AuthService.checkPermision();
	var me = AuthService.me();
	me.then(function (r) {
		$scope.name = r.name;
		$scope.surname = r.surname;
		$scope.description = r.description;
	});
})
.controller('profileEdit', function ($scope, $http, $location, AuthService, FlashService) {
	AuthService.checkPermision();
	AuthService.setToken($scope);
	var me = AuthService.me();
	me.then(function (r) {
		$scope.name = r.name;
		$scope.surname = r.surname;
		$scope.description = r.description;
	});
	$scope.edit = function () {
		$http
			.post('/api/profile', {name: $scope.name, surname: $scope.surname, description: $scope.description})
			.success(function (r) {
				FlashService.show(r[0], 'success');
				$location.path('/profile');
			})
			.error(function (r) {
				FlashService.show(r[0], 'danger');
			})
		;
	};
})
.controller('user', function ($scope, $http, $route) {
	var userId = parseInt($route.current.params.id);
	userId = isNaN(userId) ? 0 : userId;
	$http
		.get('/api/user/'+userId)
		.success(function (r) {
			$scope.name = r.name;
			$scope.surname = r.surname;
			$scope.description = r.description;
		})
	;
})
.directive('dUsersList', function ($http) {
	return {
		restrict: 'E',
		replace: true,
		templateUrl: '/templates/d/usersList.html?'+Math.random(),
		scope: {users: '@'},
		link: function (scope, ele, attr) {
			var ammount = parseInt(attr.ammount);
			ammount = isNaN(ammount) ? 10 : ammount;
			$http
				.get('/api/users/'+ammount)
				.success(function (r) {
					scope.users = r;
				})
			;
		}
	};
})
.run(function ($rootScope, AuthService, FlashService) {
	$rootScope.$on('$locationChangeStart', function () {
		AuthService.me();
		FlashService.checkCookies();
		window.s = $rootScope;
	});
});