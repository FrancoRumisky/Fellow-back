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
    
    var eventData = {
        title: $(this).data("title"),
        organizer: $(this).data("organizer"),
        startDate: $(this).data("start-date"),
        endDate: $(this).data("end-date"),
        availableSlots: $(this).data("available-slots"),
        capacity: $(this).data("capacity"),
        status: $(this).data("status"),
        description: $(this).data("description"),
        image: $(this).data("image"),
    };

    console.log("Evento seleccionado:", eventData);

    // Mostrar en el modal
    $("#eventModalTitle").text(eventData.title);
    $("#eventModalOrganizer").text(eventData.organizer);
    $("#eventModalStartDate").text(eventData.startDate);
    $("#eventModalEndDate").text(eventData.endDate);
    $("#eventModalAvailableSlots").text(eventData.availableSlots);
    $("#eventModalCapacity").text(eventData.capacity);
    $("#eventModalStatus").text(eventData.status);
    $("#eventModalDescription").text(eventData.description);
    $("#eventModalImage").attr("src", eventData.image);

    $("#viewEventModal").modal("show");
});
});