angular.module('tellers', ['lumx'])
.controller('tellersCtrl', ['$scope', '$http', 'progress', 'LxNotificationService', function($scope, $http, progress, LxNotificationService) {
  $scope.tellers = {};
  
  $http.get("/scripts/ScheidsrechtersApp/shared/GetTellers.php").success(
    function(response) {
      $scope.telschema = response.telschema;
      $scope.teloverzicht = response.teloverzicht;
      
      progress.hide();
      console.log("SetTellers:");
      console.log(response);
    }
  );
  
  $scope.saveTellers = function (date, time, code, values){
    var new_tellers = values.newValue.team;
    var old_tellers;
    var counter = 0;
    
    for (var i = 0; i < $scope.teloverzicht.length; i++){
      if ($scope.teloverzicht[i].team == new_tellers){
        $scope.teloverzicht[i].geteld++;
      }
    }
    
    if (values.oldValue != null){
      old_tellers = values.oldValue.team;
      for (var i = 0; i < $scope.teloverzicht.length; i++){
        if ($scope.teloverzicht[i].team == old_tellers){
          $scope.teloverzicht[i].geteld--;
        }
      }
    }
    
    if (values.oldValue == null || values.oldValue.team != values.newValue.team){
      $http.post("/scripts/ScheidsrechtersApp/shared/SaveTellers.php", {"date": date, "time": time, "code": code, "tellers": values.newValue.team}).
      then(function(response) {
        LxNotificationService.success(response.data);
      }, function(response) {
        LxNotificationService.info(response.data);
      });
    }
  }
  
  $scope.delTellers = function (date, time, code){
    var tellers = $scope.telschema[date][time][code].tellers.team;
    delete $scope.telschema[date][time][code].tellers;
    console.log($scope.telschema);
    
    // Haal het aantal keer geteld met 1 omlaag
    for (var i = 0; i < $scope.teloverzicht.length; i++){
      if ($scope.teloverzicht[i].team == tellers){
        $scope.teloverzicht[i].geteld--;
      }
    }
    
    // Sla wijziging op
    $http.post("/scripts/ScheidsrechtersApp/shared/SaveTellers.php", {"date": date, "time": time, "code": code, "tellers": ""}).
    then(function(response) {
      LxNotificationService.success(response.data);
    }, function(response) {
      LxNotificationService.info(response.data);
    });
  }
  
  progress.show();
}])
.directive('tellers', function() {
  return {
    restrict: 'E',
    templateUrl: '/scripts/ScheidsrechtersApp/app/settellers/settellers.html'
  };
});
