#TODO

## Add Attendance sytem.
1. student can mark thier attendance daily basis (show like monthly view)
2. owern can view students attendance by library -> room with seat

3. for ower viewing attendance of student can helps to manage their available seats (which seat has student or not)


## Multiple roles login management by owner

1. ower can create multiple staff and assign roles 

owner can create roles by select specific permissions

owner can create user login with role.

## Generate invoice and sent to student whatsapp or email 

## Role permmission (multiple user login)

 i think... the current owner permission is not seeded by default. 
1. owner  role is library owner (can access entire feature) within their libraries.
2. owner should have default all permissions. 
3. owner can create new role and then create new user with selected permissions. (seperate access controll in sidebar for owner that can manage roles and permissions)


4. Also student has default access with their login. if permission required then seed static default permissions for students. no need to manage student permissions by owner.

5. if owner create new user with role , new user can only access the assign permissions. 

6. owner manage users ( create seperate menu in sidebar for owner)

 use spatie role permission inbuilt method see docs here https://spatie.be/docs/laravel-permission/v7/basic-usage/basic-usage




- default permission for owners 
```
 return [
            'view_role',
            'create_role',
            'edit_role',
            'delete_role',
            'view_user',
            'create_user',
            'edit_user',
            'delete_user',
            'view_library',
            'edit_library',
            'view_library_shift',
            'edit_library_shift',
            'view_room',
            'create_room',
            'edit_room',
            'delete_room',
            'view_seat',
            'generate_seat',
            'view_student',
            'create_student',
            'edit_student',
            'delete_student',
            'view_membership',
            'edit_membership',
            'view_payment',
            'view_attendance',
        ];```