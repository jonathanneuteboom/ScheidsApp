angular.module('zaalwacht', ['lumx'])
.controller('zaalwachtCtrl', ['$scope', '$http', 'progress', 'LxNotificationService', function ($scope, $http, progress, LxNotificationService) {
    $http.get("/scripts/ScheidsrechtersApp/shared/GetZaalwacht.php").success(
      function (response) {
          $scope.zaalwachtoverzicht = response.zaalwachtoverzicht;
          $scope.zaalwachtschema = response.zaalwachtschema;

          progress.hide();
          console.log("Setzaalwacht:");
          console.log(response);
      }
    );

    $scope.saveZaalwacht = function (date, time, code, values) {
        var new_zaalwacht = values.newValue.team;
        var old_zaalwacht;
        var counter = 0;

        for (var i = 0; i < $scope.zaalwachtoverzicht.length; i++) {
            if ($scope.zaalwachtoverzicht[i].team == new_zaalwacht) {
                $scope.zaalwachtoverzicht[i].zaalwacht++;
            }
        }

        if (values.oldValue != null) {
            old_zaalwacht = values.oldValue.team;
            for (var i = 0; i < $scope.zaalwachtoverzicht.length; i++) {
                if ($scope.zaalwachtoverzicht[i].team == old_zaalwacht) {
                    $scope.zaalwachtoverzicht[i].zaalwacht--;
                }
            }
        }

        if (values.oldValue == null || values.oldValue.team != values.newValue.team) {
            $http.post("/scripts/ScheidsrechtersApp/shared/SaveZaalwacht.php", { "date": date, "team": values.newValue.team }).
            then(function (response) {
                LxNotificationService.success(response.data);
            }, function (response) {
                LxNotificationService.info(response.data);
            });
        }
    }

    $scope.SetWvdw = function (date, code) {
        console.log(date + "," + code);
        $http.post("/scripts/ScheidsrechtersApp/shared/SaveWvdw.php", { "date": date, "code": code })
        .then(function (response) {
            console.log(response);
            for (var key in $scope.zaalwachtschema) {
                if (key == date) {
                    for (var i = 0; i < $scope.zaalwachtschema[key]["teams"].length; i++) {
                        if ($scope.zaalwachtschema[key]["teams"][i].code == code) {
                            $scope.zaalwachtschema[key]["teams"][i].wvdw = true;
                        }
                        else {
                            $scope.zaalwachtschema[key]["teams"][i].wvdw = false;
                        }
                    }
                    LxNotificationService.success(response.data);
                    break;
                }
            }
        }, function (response) {
            console.log(response);
            LxNotificationService.info(response.data);
        });
    }

    $scope.delZaalwacht = function (date, id) {
        var zaalwacht = $scope.zaalwachtschema[date].zaalwacht.team;
        delete $scope.zaalwachtschema[date].zaalwacht;
        console.log($scope.zaalwachtschema);

        // Haal het aantal keer zaalwacht met 1 omlaag
        for (var i = 0; i < $scope.zaalwachtoverzicht.length; i++) {
            if ($scope.zaalwachtoverzicht[i].team == zaalwacht) {
                $scope.zaalwachtoverzicht[i].zaalwacht--;
            }
        }

        // Sla wijziging op
        $http.post("/scripts/ScheidsrechtersApp/shared/DelZaalwacht.php", { "id": id }).
        then(function (response) {
            LxNotificationService.success(response.data);
        }, function (response) {
            LxNotificationService.info(response.data);
        });
    }

    progress.show();
}])
.directive('zaalwacht', function () {
    return {
        restrict: 'E',
        templateUrl: '/scripts/ScheidsrechtersApp/app/setzaalwacht/setzaalwacht.html'
    };
});
