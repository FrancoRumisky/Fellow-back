$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".eventSideA").addClass("activeLi");

    $("#eventsTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "POST",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6, 7],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}eventsList`,
            data: function (data) {},
        },
    });

    // Mostrar los detalles del evento en el modal
    $("#eventsTable").on("click", ".viewEvent", function (e) {
        e.preventDefault();

        var title = $(this).data("title");
        var organizer = $(this).data("organizer");
        var startDate = $(this).data("start-date");
        var endDate = $(this).data("end-date");
        var availableSlots = $(this).data("available-slots");
        var capacity = $(this).data("capacity");
        var status = $(this).data("status");
        var description = $(this).data("description");
        var image = $(this).data("image");

        $("#eventModalTitle").text(title);
        $("#eventModalOrganizer").text(organizer);
        $("#eventModalStartDate").text(startDate);
        $("#eventModalEndDate").text(endDate);
        $("#eventModalAvailableSlots").text(availableSlots);
        $("#eventModalCapacity").text(capacity);
        $("#eventModalStatus").text(status);
        $("#eventModalDescription").text(description);
        $("#eventModalImage").attr("src", image);

        $("#viewEventModal").modal("show");
    });
});