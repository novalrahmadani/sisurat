import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\LetterheadController::edit
 * @see app/Http/Controllers/LetterheadController.php:15
 * @route '/master-data/letterhead'
 */
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/master-data/letterhead',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\LetterheadController::edit
 * @see app/Http/Controllers/LetterheadController.php:15
 * @route '/master-data/letterhead'
 */
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LetterheadController::edit
 * @see app/Http/Controllers/LetterheadController.php:15
 * @route '/master-data/letterhead'
 */
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\LetterheadController::edit
 * @see app/Http/Controllers/LetterheadController.php:15
 * @route '/master-data/letterhead'
 */
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\LetterheadController::edit
 * @see app/Http/Controllers/LetterheadController.php:15
 * @route '/master-data/letterhead'
 */
    const editForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\LetterheadController::edit
 * @see app/Http/Controllers/LetterheadController.php:15
 * @route '/master-data/letterhead'
 */
        editForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\LetterheadController::edit
 * @see app/Http/Controllers/LetterheadController.php:15
 * @route '/master-data/letterhead'
 */
        editForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    edit.form = editForm
/**
* @see \App\Http\Controllers\LetterheadController::update
 * @see app/Http/Controllers/LetterheadController.php:24
 * @route '/master-data/letterhead'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: '/master-data/letterhead',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\LetterheadController::update
 * @see app/Http/Controllers/LetterheadController.php:24
 * @route '/master-data/letterhead'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\LetterheadController::update
 * @see app/Http/Controllers/LetterheadController.php:24
 * @route '/master-data/letterhead'
 */
update.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\LetterheadController::update
 * @see app/Http/Controllers/LetterheadController.php:24
 * @route '/master-data/letterhead'
 */
    const updateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\LetterheadController::update
 * @see app/Http/Controllers/LetterheadController.php:24
 * @route '/master-data/letterhead'
 */
        updateForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(options),
            method: 'post',
        })
    
    update.form = updateForm
const letterhead = {
    edit: Object.assign(edit, edit),
update: Object.assign(update, update),
}

export default letterhead
