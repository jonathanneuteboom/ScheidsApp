angular.module('availability', ['lumx'])
.controller('availabilityCtrl', ['$scope', '$http', 'progress', 'LxNotificationService', 'LxProgressService', function($scope, $http, progress, LxNotificationService, LxProgressService) {
  $http.get("/scripts/ScheidsrechtersApp/shared/GetMatches.php").success(
    function(response) {
      $scope.data = response;
      progress.hide();
      console.log("GetMatches:");
      console.log(response);
    }
  );
  
  $scope.getFirstKey = function ( data ) {
      for ( elem in data )
          return elem;
  }
  
  $scope.remarks = [];
  $scope.availibility = [];
  
  $scope.Saveremarks = function (date, time){
    var remarks = $scope.data[date][time]['remarks'];
    $http.post("/scripts/ScheidsrechtersApp/shared/SaveRemarks.php", {"date": date, "time": time, "remarks": remarks}).
    then(function(response) {
      LxNotificationService.success(response.data);
    }, function(response) {
      LxNotificationService.info(response.data);
    });
  }
  
  $scope.SaveAvailability = function (date, time, availability){
    $http.post("/scripts/ScheidsrechtersApp/shared/SaveAvailability.php", {"date": date, "time": time, "availability": availability}).
    then(function(response) {
      if (response.data == "Opgeslagen"){
        $scope.data[date][time]['availability'] = availability;
        LxNotificationService.success(response.data);
      }
      else {
        LxNotificationService.error(response.data);
      }
    }, function(response) {
      LxNotificationService.info(response.data);
    });
  }
  
  progress.show();
}])
.directive('availability', function() {
  return {
    restrict: 'E',
    templateUrl: '/scripts/ScheidsrechtersApp/app/availability/availability.html'
  };
});
