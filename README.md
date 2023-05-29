# InterrapidisimoPrueba

- Prueba tecnica 
- Entregada por: Mauricio Esguerra
- Correo electronico: mee20054@gmail.com

## Requsitos

+ Version Magento compatible: 2.4.0 Community
+ PHP: 7.4
+ MySQL: 8.0
+ Elasticsearch: 7.x.x

## Instalacion

+ Descargar el repositorio para poder obtener la versión y poder visualizarla de manera local.
+ Se debe realizar el copiado de las mismas en el directorio **<<root_magento>>/app/code**.
+ Ejecutar los comandos de instalacion:
  -  Actualización de Magento para cargar cambios en el proyecto: **bin/magento setup:upgrade**  . 
  -  Despliegue de archivos estaticos: **bin/magento setup:static-content:deploy -f** .  
  -  Inyeccion de dependecias para incorporar los cambios: **bin/magento setup:di:compile**  .
  -  Limpieza de cache: **bin/magento cache:clean** .
  -  Vaciado de los almacenamientos usados por el cache: **bin/magento cache:flush**.
+ Ingresar a Magento admin.


## Funcionamiento

+ Ingresar al modulo de administracion de Magento (Se recomienda ingresar inicialmente con perfil de administrador).
+ Ingresar al menu **Stores** -> **Configuration** y en las opciones que se despliegan buscar la pestaña **Sales** opcion de **Delivery Methods**. Alli seleccionar el metodo **Intershipping** , habilitarlo y setear un **Default Price** para que tenga un valor por defecto. El metodo de **Free Shipping** debe estar deshabilitado.
+ En el menu izquierdo se debe observar una opcion llamada **INTERRAPIDISIMO** y al desplegar las opciones seleccionar la opcion llamada **Costos por ciudad**.
+ Una vez alli, darle clic en el boton Add new Citycost y crear una nueva opcion. En el formulario que se despliega Ingresar el nombre de la ciudad (Igual como se este cargando en el Shipping Address del checkout) el precio y si esta activo o no. Finalizando con guardar.
+ Ingresar al storefron con un usuario autenticado. En el Shipping Address seleccionar la ciudad parametrizada y ver el valor guardado.
+ Si se crea mas de una direccion para el cliente con ciudad parametrizada debe cambiar el valor del precio de acuerdo a la tabla de parametria. En caso contrario debe presentarse el valor del precio por defecto.

