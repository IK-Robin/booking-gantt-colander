this code work only on the plugin rv-booking-system plugin and use three database to work this wordpress plugin the database are 
1.wp_rvbs_rv_lots
2. wp_rvbs_bookings
3.wp_rvbs_booking_counts


4. input search: if no user find in the user input fild then add a new user when submiting the form and stor the user in wp_rvbs_bookings table 

5. after createing the user and make a booking form the admin then should be send an email with the password and the user name to the user and send the login url as a subscriber 

6. if the booking cancel then it should be not showing in the calender and the booking should be cancel from the database and send an email to the user that the booking is cancel 
7. add a new page for showing the cancell pooking day contain all the grantt-bookings.js file just change the condition to show the cancel booking day only
here is the condition  


   const bookingsForDay = lot.bookings.filter((booking) => {
                return (
                    day >= booking.start_day &&
                    day <= booking.end_day &&
                    ['cancelled'].includes(booking.status)
                );
            });


8. add search functionality to the hole calander and search by date and by name of the user and by status of the booking and by lot name and by lot id and by lot type and by lot price and by lot capacity and by lot category and and date rang  

9. load all data inside the data attribut to improve the loading spreed in font end only and load the data when the user click on the date in the calender and load the data 
