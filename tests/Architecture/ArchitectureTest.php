<?php

arch()->preset()->php();

arch()->expect('App')->classes()->toBeFinal();
