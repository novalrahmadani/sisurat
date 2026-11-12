import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PublicPmbProspectController::join
 * @see app/Http/Controllers/PublicPmbProspectController.php:18
 * @route '/pendaftaran-calon-mahasiswa'
 */
export const join = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: join.url(options),
    method: 'get',
})

join.definition = {
    methods: ["get","head"],
    url: '/pendaftaran-calon-mahasiswa',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PublicPmbProspectController::join
 * @see app/Http/Controllers/PublicPmbProspectController.php:18
 * @route '/pendaftaran-calon-mahasiswa'
 */
join.url = (options?: RouteQueryOptions) => {
    return join.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicPmbProspectController::join
 * @see app/Http/Controllers/PublicPmbProspectController.php:18
 * @route '/pendaftaran-calon-mahasiswa'
 */
join.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: join.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PublicPmbProspectController::join
 * @see app/Http/Controllers/PublicPmbProspectController.php:18
 * @route '/pendaftaran-calon-mahasiswa'
 */
join.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: join.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PublicPmbProspectController::join
 * @see app/Http/Controllers/PublicPmbProspectController.php:18
 * @route '/pendaftaran-calon-mahasiswa'
 */
    const joinForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: join.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PublicPmbProspectController::join
 * @see app/Http/Controllers/PublicPmbProspectController.php:18
 * @route '/pendaftaran-calon-mahasiswa'
 */
        joinForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: join.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PublicPmbProspectController::join
 * @see app/Http/Controllers/PublicPmbProspectController.php:18
 * @route '/pendaftaran-calon-mahasiswa'
 */
        joinForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: join.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    join.form = joinForm
/**
* @see \App\Http\Controllers\PublicPmbProspectController::submit
 * @see app/Http/Controllers/PublicPmbProspectController.php:35
 * @route '/pendaftaran-calon-mahasiswa'
 */
export const submit = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

submit.definition = {
    methods: ["post"],
    url: '/pendaftaran-calon-mahasiswa',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PublicPmbProspectController::submit
 * @see app/Http/Controllers/PublicPmbProspectController.php:35
 * @route '/pendaftaran-calon-mahasiswa'
 */
submit.url = (options?: RouteQueryOptions) => {
    return submit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublicPmbProspectController::submit
 * @see app/Http/Controllers/PublicPmbProspectController.php:35
 * @route '/pendaftaran-calon-mahasiswa'
 */
submit.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PublicPmbProspectController::submit
 * @see app/Http/Controllers/PublicPmbProspectController.php:35
 * @route '/pendaftaran-calon-mahasiswa'
 */
    const submitForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: submit.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PublicPmbProspectController::submit
 * @see app/Http/Controllers/PublicPmbProspectController.php:35
 * @route '/pendaftaran-calon-mahasiswa'
 */
        submitForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: submit.url(options),
            method: 'post',
        })
    
    submit.form = submitForm
const PublicPmbProspectController = { join, submit }

export default PublicPmbProspectController