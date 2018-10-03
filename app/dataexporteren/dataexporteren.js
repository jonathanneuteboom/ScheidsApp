'use strict';

angular.module('dataexporteren', ['lumx'])
.controller('dataexporterenCtrl', ['$scope', '$http', 'LxNotificationService', 'LxProgressService', 'LxDialogService', 'progress', function($scope, $http, LxNotificationService, LxProgressService, LxDialogService, progress){
  $http.get("/scripts/ScheidsrechtersApp/shared/GetExports.php").success(
    function(response) {
      console.log("GetExports:");
      console.log(response);
      $scope.ical_file = response.ical_file;
      $scope.xlsx = response.xlsx;
      progress.hide();
    }
  );
  
  progress.show();
}])
.directive('dataexporteren', function() {
  return {
    restrict: 'E',
    templateUrl: '/scripts/ScheidsrechtersApp/app/dataexporteren/dataexporteren.html'
  };
});
