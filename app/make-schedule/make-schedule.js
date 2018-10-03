'use strict';

angular.module('make-schedule', ['lumx'])
.controller('make-scheduleCtrl', ['$scope', '$http', 'LxNotificationService', 'LxProgressService', 'LxDialogService', 'progress', function ($scope, $http, LxNotificationService, LxProgressService, LxDialogService, progress) {
    $http.get("/scripts/ScheidsrechtersApp/shared/GetIndelen.php").success(
      function (response) {
          $scope.overzicht = response.overzicht;
          $scope.scheidsrechters = response.scheidsrechters;
          $scope.geflotenPerTeam = response.geflotenPerTeam;
          $scope.teamPerScheids = response.teamPerScheids;
          $scope.results = response.results;
          console.log("GetIndelen:");
          console.log(response);
          progress.hide();
          if (response.error != null) {
              LxNotificationService.info(response.error);
          }
      }
    );

    var activeDate, activeTime, activeCode;

    $scope.openRefereeDialog = function (date, time, code) {
        activeDate = date;
        activeTime = time;
        activeCode = code;
        $scope.activeMatch = $scope.overzicht[date][time][code];
        LxDialogService.open('refereeDialog');
    }

    $scope.delReferee = function (date, time, code, user_id) {
        $http.post("/scripts/ScheidsrechtersApp/shared/DelReferee.php", { "code": code, "user_id": user_id }).
        then(function (response) {
            LxNotificationService.success(response.data);
            if (response.data == 'Opgeslagen') {
                $scope.overzicht[date][time][code].scheids = null;
                $scope.scheidsrechters[user_id].count--;
            }
        }, function (response) {
            LxNotificationService.info(response.data);
        });
    }

    $scope.saveReferee = function (user_id) {
        LxDialogService.close('refereeDialog');
        $http.post("/scripts/ScheidsrechtersApp/shared/SaveReferee.php", { "date": activeDate, "time": activeTime, "code": activeCode, "user_id": user_id }).
        then(function (response) {
            LxNotificationService.success(response.data);
            if (response.data == 'Opgeslagen') {
                var wedstrijden = $scope.overzicht[activeDate][activeTime];
                angular.forEach(wedstrijden, function (value, key) {
                    if (wedstrijden[key].scheids != null && wedstrijden[key].scheids == user_id) {
                        wedstrijden[key].scheids = null;
                        $scope.scheidsrechters[user_id].count--;
                    }
                });

                $scope.scheidsrechters[user_id].count++;
                wedstrijden[activeCode].scheids = user_id;
            }

            activeCode = null;
        }, function (response) {
            console.log(response);
            LxNotificationService.info(response.data);
            activeDate = null;
            activeTime = null;
            activeCode = null;
        });
    }

    $scope.SendMailAndWhatsapps = function () {
        // FIRST CONFIRMATION
        LxNotificationService.confirm('Mail bevestiging', 'Weet je zeker dat je de teams voor de komende week een mail wilt sturen?', { cancel: 'Annuleren', ok: 'Verzenden' }, function (answer) {
            if (answer) {
                // Start the spinner
                progress.show();
                $http.post("/scripts/ScheidsrechtersApp/shared/SendMailAndWhatsapps.php").
                then(function (response) {
                    progress.hide(); console.log(response);
                    if (response.data == 'Verzonden') {
                        LxNotificationService.success(response.data);
                    }
                    else {
                        LxNotificationService.info(response.data);
                    }
                }, function (response) {
                    progress.show();
                    LxNotificationService.info(response.data);
                });
            }
        });
    };

    $scope.openGeflotenPerTeamDialog = function () {
        LxDialogService.open('geflotenPerTeam');
    }

    $scope.teams = ["Dames 1",
                    "Dames 2",
                    "Dames 3",
                    "Dames 4",
                    "Dames 5",
                    "Dames 6",
                    "Dames 7",
                    "Dames 8",
                    "Dames 9",
                    "Dames 10",
                    "Dames 11",
                    "Dames 12",
                    "Dames 13",
                    "Heren 1",
                    "Heren 2",
                    "Heren 3",
                    "Heren 4",
                    "Heren 5",
                    "Heren 6"];

    progress.show();
}])
.directive('makeschedule', function () {
    return {
        restrict: 'E',
        templateUrl: '/scripts/ScheidsrechtersApp/app/make-schedule/make-schedule.html'
    };
});
