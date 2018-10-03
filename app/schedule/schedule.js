'use strict';
angular.module('schedule', ['lumx'])
.controller('scheduleCtrl', function($scope, $http, progress) {
  $http.get("/scripts/ScheidsrechtersApp/shared/GetOverzicht.php").success(
    function(response) {
      $scope.overzicht = response.overzicht;
      $scope.scheidsrechters = response.scheidsrechters;
      console.log("GetOverzicht:");
      console.log(response);
      progress.hide();
    }
  );
  
  progress.show();
})
.directive('schedule', function() {
  return {
    restrict: 'E',
    templateUrl: '/scripts/ScheidsrechtersApp/app/schedule/schedule.html'
  };
});
